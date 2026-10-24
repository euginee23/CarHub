---
paths:
  - 'app/Actions/Companies/**'
---

# Companies

## Rental companies are verified once, then trusted to list
A rental company applies through SubmitCompanyApplication with company_name, business_address, a Mayor's / Business permit (DocumentType::BusinessPermit) and a representative's government ID. No OR/CR is collected. ReviewCompanyApplication::approve sets company_verified_at and copies company_name/company_address onto the user. After that, vehicles go live when the company publishes them: there is deliberately no per-vehicle admin approval, only ModerateVehicle take-downs after the fact.
