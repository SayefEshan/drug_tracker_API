# Drug Finder API - Quick Start Guide

## 🚀 Setup (5 minutes)

```bash
# 1. Navigate to project directory
cd /Users/sayef/Developer/drug-finder

# 2. Install dependencies (if not already done)
composer install

# 3. Set up environment
cp .env.example .env
php artisan key:generate

# 4. Create MySQL database
mysql -u root -p -e "CREATE DATABASE drug_finder CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 5. Configure database in .env (update DB_PASSWORD if needed)
# DB_CONNECTION=mysql
# DB_DATABASE=drug_finder
# DB_USERNAME=root
# DB_PASSWORD=

# 6. Run migrations
php artisan migrate

# 7. Start server
php artisan serve
```

Server will be running at: `http://localhost:8000`

## 📋 Testing the API

### Option 1: Run Automated Tests
```bash
php artisan test
```
Expected: **27 passing tests**

### Option 2: Use Postman Collection

1. Open Postman
2. Import `Drug_Finder_API.postman_collection.json`
3. Run the collection in order:
   - Register User → Login User → Search Drugs → Add Medication

## 🎯 Quick API Test (using curl)

### 1. Register a User
```bash
curl -X POST http://localhost:8000/api/register \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "name": "Test User",
    "email": "test@example.com",
    "password": "password123"
  }'
```

Save the `access_token` from the response.

### 2. Search for Drugs (No auth required)
```bash
curl -X GET "http://localhost:8000/api/drugs/search?drug_name=aspirin" \
  -H "Accept: application/json"
```

### 3. Add Medication (Replace YOUR_TOKEN)
```bash
curl -X POST http://localhost:8000/api/medications \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "rxcui": "243670"
  }'
```

### 4. View Your Medications
```bash
curl -X GET http://localhost:8000/api/medications \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"
```

## 📊 Project Highlights

### ✅ All Requirements Met

**Core Features:**
- ✓ User Authentication (Register, Login, Logout)
- ✓ Public Drug Search (unauthenticated)
- ✓ Private Medication Management (add, view, delete)
- ✓ RxNorm API Integration (getDrugs, getRxcuiHistoryStatus)
- ✓ RXCUI Validation
- ✓ Duplicate Prevention

**Bonus Features:**
- ✓ Rate Limiting (60 req/min on search endpoint)
- ✓ Caching (24-hour TTL for RxNorm responses)

**Code Quality:**
- ✓ Clean Architecture (Controller → Service → Model)
- ✓ Comprehensive Testing (27 tests, high coverage)
- ✓ Error Handling & Validation
- ✓ Security (Sanctum, bcrypt, SQL injection prevention)
- ✓ Documentation (README + Postman Collection)

### 📁 Key Files

```
drug-finder/
├── README.md                          # Complete documentation
├── Drug_Finder_API.postman_collection.json  # Postman collection
├── app/
│   ├── Clients/
│   │   └── RxNormClient.php           # RxNorm HTTP client
│   ├── Http/Controllers/
│   │   ├── AuthController.php         # Authentication
│   │   ├── DrugSearchController.php   # Drug search
│   │   └── UserMedicationController.php  # Medications
│   ├── Models/
│   │   ├── User.php
│   │   └── UserMedication.php
│   └── Services/
│       └── RxNormService.php          # Business logic layer
├── routes/api.php                     # API routes
└── tests/                             # 27 passing tests
```

## 🎓 API Endpoints Summary

| Endpoint | Method | Auth | Description |
|----------|--------|------|-------------|
| `/api/register` | POST | No | Register new user |
| `/api/login` | POST | No | Login user |
| `/api/logout` | POST | Yes | Logout user |
| `/api/drugs/search` | GET | No | Search drugs (rate limited) |
| `/api/medications` | GET | Yes | Get user's medications |
| `/api/medications` | POST | Yes | Add medication |
| `/api/medications/{id}` | DELETE | Yes | Delete medication |

## 📈 Test Coverage

```bash
# Run tests with coverage
php artisan test --coverage
```

**Coverage Areas:**
- Authentication flows (register, login, logout)
- Drug search with validation
- Medication CRUD operations
- Authorization checks
- Rate limiting
- Caching behavior
- Error handling

## 🔍 Troubleshooting

**Database locked error:**
```bash
php artisan migrate:fresh
```

**Clear cache:**
```bash
php artisan cache:clear
php artisan config:clear
```

**Run tests in isolation:**
```bash
php artisan test --filter=AuthTest
php artisan test --filter=DrugSearchTest
php artisan test --filter=UserMedicationTest
```

## 📚 Next Steps

1. Review the full [README.md](README.md)
2. Import and explore the Postman Collection
3. Run the test suite
4. Test the live API endpoints

## 🎉 Success Criteria Checklist

- ✅ User registration and authentication working
- ✅ Public drug search endpoint functional
- ✅ Private medication endpoints secured
- ✅ Rate limiting active
- ✅ Caching implemented
- ✅ Tests passing (27/27)
- ✅ Documentation complete
- ✅ Postman collection ready

---

**Need help?** Check README.md or review the test files for usage examples.
