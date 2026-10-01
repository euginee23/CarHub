---
paths:
  - 'app/Services/Reports/**'
---

# Reports

## Reports, charts, and the activity log
All report figures come from App\Services\Reports\RentalReports for a date range. "Collected" counts only Paid payments by paid_at, so refunded and refund-due payments are excluded. Utilization is rented vehicle-days over fleet × days. dailyDemandByType() is the demand history for forecasting. CSV exports are ReportExportController::REPORTS. Charts are Blade components (x-chart.columns, x-chart.bars): single series in brand-600 (validated against white), thin bars with rounded data ends, hairline grid, hover/focus tooltips, and a "Show as table" fallback. Don't add dual axes or multi-colour series without running the dataviz palette validator. Important state changes write to the audit trail via App\Support\ActivityLogger::record() inside the action, not the view. ActivityLog is pruned after 365 days.
