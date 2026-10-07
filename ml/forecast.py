"""
CarHub demand forecaster: one small LSTM per vehicle body type.

Laravel (`php artisan demand:forecast`) exports the daily number of booking
requests per body type as a CSV and runs this script, which writes a JSON
forecast that Laravel stores in the `demand_forecasts` table.

Input CSV (wide):   date,Sedan,SUV,Van,...      one row per day, oldest first
Output JSON:        {"model": "lstm", "series": {"SUV": {"forecast": [{"date": "...", "value": 1.4}, ...],
                     "val_mae": 0.41, "train_windows": 300}, ...}, "skipped": {"Van": "reason"}}

Each model sees the last `lookback` days (scaled requests + day of week) and
predicts the next day; the forecast is produced recursively for `horizon` days.
The last 20% of windows are held out to report a validation MAE in requests/day.

Run directly:  ml/.venv/bin/python ml/forecast.py --input demand.csv --output forecast.json
"""

import argparse
import json
import os
import sys
from datetime import datetime, timezone

os.environ.setdefault("TF_CPP_MIN_LOG_LEVEL", "3")

import numpy as np  # noqa: E402
import pandas as pd  # noqa: E402
import tensorflow as tf  # noqa: E402

MODEL_VERSION = "lstm-v1"


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Forecast CarHub booking demand per body type with an LSTM.")
    parser.add_argument("--input", required=True, help="Wide CSV: date column plus one column per body type.")
    parser.add_argument("--output", required=True, help="Where to write the JSON forecast.")
    parser.add_argument("--horizon", type=int, default=30, help="Days to forecast.")
    parser.add_argument("--lookback", type=int, default=28, help="Days of history each prediction sees.")
    parser.add_argument("--epochs", type=int, default=60, help="Maximum training epochs (early stopping applies).")
    parser.add_argument("--min-days", type=int, default=90, help="Least history a series needs to be modelled.")
    parser.add_argument("--seed", type=int, default=42, help="Random seed, for repeatable results.")
    return parser.parse_args()


def day_of_week_features(dates: pd.DatetimeIndex) -> np.ndarray:
    """One-hot day of week (Mon..Sun), so the model can learn weekly patterns."""
    one_hot = np.zeros((len(dates), 7), dtype=np.float32)
    one_hot[np.arange(len(dates)), dates.dayofweek] = 1.0
    return one_hot


def build_model(lookback: int, features: int) -> tf.keras.Model:
    model = tf.keras.Sequential([
        tf.keras.layers.Input(shape=(lookback, features)),
        tf.keras.layers.LSTM(32),
        tf.keras.layers.Dropout(0.1),
        tf.keras.layers.Dense(16, activation="relu"),
        tf.keras.layers.Dense(1),
    ])
    model.compile(optimizer=tf.keras.optimizers.Adam(learning_rate=0.005), loss="mse")
    return model


def forecast_series(values: np.ndarray, dates: pd.DatetimeIndex, args: argparse.Namespace) -> dict:
    """Train an LSTM on one body type's daily requests and forecast the horizon."""
    scale = float(values.max()) or 1.0
    scaled = (values / scale).astype(np.float32)
    inputs = np.concatenate([scaled[:, None], day_of_week_features(dates)], axis=1)

    windows = np.stack([inputs[i:i + args.lookback] for i in range(len(inputs) - args.lookback)])
    targets = scaled[args.lookback:]

    split = int(len(windows) * 0.8)
    model = build_model(args.lookback, inputs.shape[1])
    model.fit(
        windows[:split], targets[:split],
        validation_data=(windows[split:], targets[split:]),
        epochs=args.epochs,
        batch_size=32,
        verbose=0,
        callbacks=[tf.keras.callbacks.EarlyStopping(monitor="val_loss", patience=8, restore_best_weights=True)],
    )

    held_out = model.predict(windows[split:], verbose=0).ravel()
    val_mae = float(np.mean(np.abs(held_out - targets[split:])) * scale)

    # Predict one day at a time, feeding each prediction back in as history.
    history = inputs[-args.lookback:].copy()
    future_dates = pd.date_range(dates[-1] + pd.Timedelta(days=1), periods=args.horizon, freq="D")
    future_dow = day_of_week_features(future_dates)
    forecast = []

    for step, date in enumerate(future_dates):
        predicted = float(model.predict(history[None, ...], verbose=0)[0, 0])
        predicted = max(0.0, predicted)
        forecast.append({"date": date.strftime("%Y-%m-%d"), "value": round(predicted * scale, 3)})
        next_row = np.concatenate([[predicted], future_dow[step]]).astype(np.float32)
        history = np.vstack([history[1:], next_row])

    return {"forecast": forecast, "val_mae": round(val_mae, 3), "train_windows": int(split)}


def main() -> int:
    args = parse_args()
    tf.keras.utils.set_random_seed(args.seed)

    try:
        frame = pd.read_csv(args.input, parse_dates=["date"]).sort_values("date").set_index("date")
    except (OSError, ValueError, KeyError) as error:
        print(f"Could not read {args.input}: {error}", file=sys.stderr)
        return 2

    frame = frame.asfreq("D", fill_value=0)
    result = {
        "model": "lstm",
        "version": MODEL_VERSION,
        "generated_at": datetime.now(timezone.utc).isoformat(),
        "lookback": args.lookback,
        "horizon": args.horizon,
        "series": {},
        "skipped": {},
    }

    for column in frame.columns:
        values = frame[column].astype(float).to_numpy()

        if len(values) < max(args.min_days, args.lookback + 10):
            result["skipped"][column] = f"only {len(values)} days of history (needs {args.min_days})"
            continue

        if values.sum() < 10:
            result["skipped"][column] = "too few requests to learn from"
            continue

        result["series"][column] = forecast_series(values, frame.index, args)

    with open(args.output, "w", encoding="utf-8") as handle:
        json.dump(result, handle)

    return 0


if __name__ == "__main__":
    sys.exit(main())
