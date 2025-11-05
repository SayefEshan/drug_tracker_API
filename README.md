# Drug Finder - Drug Search and Tracker API

A Laravel-based RESTful API service for searching drug information and tracking user-specific medications. The service integrates with the National Library of Medicine's RxNorm APIs.

## Features

- 🔐 **User Authentication** - Secure registration and login using Laravel Sanctum
- 🔍 **Public Drug Search** - Search drugs from RxNorm database (unauthenticated)
- 💊 **Medication Tracking** - Authenticated users can maintain a personal medication list
- ⚡ **Rate Limiting** - Protects the search endpoint from abuse (60 requests/minute)
- 🚀 **Caching** - Improves performance by caching RxNorm API responses (24-hour TTL)
- ✅ **Comprehensive Testing** - 27 passing tests with high coverage
- 📚 **Complete API Documentation** - Detailed endpoint documentation with examples

## Requirements

- PHP 8.2 or higher
- Composer
- MySQL 8.0 or higher
- Git

## Installation

### 1. Clone the Repository

```bash
git clone <your-repository-url>
cd drug-finder
```

### 2. Install Dependencies

```bash
composer install
```

### 3. Environment Configuration

```bash
cp .env.example .env
php artisan key:generate
```

The application uses MySQL. Update the `.env` file with your database credentials:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=drug_finder
DB_USERNAME=root
DB_PASSWORD=your_password
```

### 4. Create Database

```bash
mysql -u root -p -e "CREATE DATABASE drug_finder CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

### 5. Run Migrations

```bash
php artisan migrate
```

### 6. Start the Development Server

```bash
php artisan serve
```

The API will be available at `http://localhost:8000`.

## API Endpoints

### Base URL

```
http://localhost:8000/api
```

### Authentication Endpoints

#### 1. Register User

**POST** `/register`

Register a new user account.

**Request Body:**
```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "password123"
}
```

**Response (201 Created):**
```json
{
  "message": "User registered successfully",
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com"
  },
  "access_token": "1|abc123...",
  "token_type": "Bearer"
}
```

**Validation Rules:**
- `name`: required, string, max 255 characters
- `email`: required, valid email, unique, max 255 characters
- `password`: required, minimum 8 characters

---

#### 2. Login User

**POST** `/login`

Authenticate an existing user.

**Request Body:**
```json
{
  "email": "john@example.com",
  "password": "password123"
}
```

**Response (200 OK):**
```json
{
  "message": "Login successful",
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com"
  },
  "access_token": "2|xyz789...",
  "token_type": "Bearer"
}
```

**Error Response (422 Unprocessable Entity):**
```json
{
  "message": "The provided credentials are incorrect.",
  "errors": {
    "email": ["The provided credentials are incorrect."]
  }
}
```

---

#### 3. Logout User

**POST** `/logout`

**Authentication:** Required (Bearer Token)

Logout the authenticated user and revoke the current access token.

**Headers:**
```
Authorization: Bearer {your_access_token}
```

**Response (200 OK):**
```json
{
  "message": "Logged out successfully"
}
```

---

### Public Drug Search Endpoint

#### 4. Search Drugs

**GET** `/drugs/search?drug_name={name}`

Search for drugs by name. Returns top 5 results from RxNorm database.

**Authentication:** Not required

**Rate Limit:** 60 requests per minute

**Query Parameters:**
- `drug_name` (required): Drug name to search (minimum 2 characters)

**Example Request:**
```
GET /api/drugs/search?drug_name=aspirin
```

**Response (200 OK):**
```json
{
  "message": "Drugs retrieved successfully",
  "data": [
    {
      "rxcui": "243670",
      "name": "Aspirin 81 MG Oral Tablet",
      "base_names": ["Aspirin"],
      "dose_form_group_names": ["Oral Tablet"]
    },
    {
      "rxcui": "198467",
      "name": "Aspirin 325 MG Oral Tablet",
      "base_names": ["Aspirin"],
      "dose_form_group_names": ["Oral Tablet"]
    }
  ]
}
```

**Error Responses:**

*Validation Error (422):*
```json
{
  "message": "The drug name field is required.",
  "errors": {
    "drug_name": ["The drug name field is required."]
  }
}
```

*Rate Limit Exceeded (429):*
```json
{
  "message": "Too Many Attempts."
}
```

---

### Private Medication Endpoints

All medication endpoints require authentication via Bearer token.

**Headers:**
```
Authorization: Bearer {your_access_token}
Content-Type: application/json
```

---

#### 5. Get User Medications

**GET** `/medications`

**Authentication:** Required

Retrieve all medications in the authenticated user's list.

**Response (200 OK):**
```json
{
  "message": "Medications retrieved successfully",
  "data": [
    {
      "id": 1,
      "rxcui": "243670",
      "drug_name": "Aspirin 81 MG Oral Tablet",
      "base_names": ["Aspirin"],
      "dose_form_group_names": ["Oral Tablet"],
      "added_at": "2025-11-05 12:34:56"
    }
  ]
}
```

---

#### 6. Add Medication

**POST** `/medications`

**Authentication:** Required

Add a drug to the user's medication list.

**Request Body:**
```json
{
  "rxcui": "243670"
}
```

**Response (201 Created):**
```json
{
  "message": "Medication added successfully",
  "data": {
    "id": 1,
    "rxcui": "243670",
    "drug_name": "Aspirin 81 MG Oral Tablet",
    "base_names": ["Aspirin"],
    "dose_form_group_names": ["Oral Tablet"],
    "added_at": "2025-11-05 12:34:56"
  }
}
```

**Error Responses:**

*Invalid RXCUI (422):*
```json
{
  "message": "Invalid RXCUI. The drug does not exist or is not active.",
  "errors": {
    "rxcui": ["The provided RXCUI is invalid or inactive."]
  }
}
```

*Duplicate Medication (409):*
```json
{
  "message": "This medication is already in your list",
  "data": { ... }
}
```

---

#### 7. Delete Medication

**DELETE** `/medications/{id}`

**Authentication:** Required

Remove a medication from the user's list.

**URL Parameters:**
- `id`: Medication ID

**Example:**
```
DELETE /api/medications/1
```

**Response (200 OK):**
```json
{
  "message": "Medication deleted successfully"
}
```

**Error Response (404):**
```json
{
  "message": "Medication not found in your list"
}
```

---

## Testing

The application includes comprehensive tests with 27 passing tests covering:
- User authentication (registration, login, logout)
- Drug search functionality
- Medication management (add, view, delete)
- Rate limiting
- Caching
- Authorization and validation

### Run Tests

```bash
php artisan test
```

### Run Tests with Coverage

```bash
php artisan test --coverage
```

## Architecture & Features

### Service Layer

The application uses a service layer pattern with `RxNormService` handling all interactions with the RxNorm API:
- Drug search with filtering for SBD (Semantic Branded Drug) types
- RXCUI validation
- Drug detail retrieval with ingredient and dose form information

### Caching Strategy

All RxNorm API calls are cached for 24 hours to:
- Reduce API load and response times
- Improve application performance
- Minimize external API dependencies

### Rate Limiting

The public search endpoint is rate-limited to 60 requests per minute per IP address to prevent abuse.

### Security

- Password hashing using bcrypt
- API authentication using Laravel Sanctum
- Token-based authentication for protected endpoints
- Input validation on all endpoints
- SQL injection prevention through Eloquent ORM

### Database Schema

**users table:**
- id, name, email, password, timestamps

**user_medications table:**
- id, user_id (FK), rxcui, drug_name, base_names (JSON), dose_form_group_names (JSON), timestamps
- Unique constraint on (user_id, rxcui) to prevent duplicates

**personal_access_tokens table:**
- Sanctum tokens for API authentication

## Technologies Used

- **Laravel 12** - PHP Framework
- **Laravel Sanctum** - API Authentication
- **MySQL 8.0** - Relational Database
- **RxNorm API** - Drug Information Database
- **PHPUnit** - Testing Framework
- **HTTP Client** - Laravel's HTTP facade for API calls

## Project Structure

```
drug-finder/
├── app/
│   ├── Http/
│   │   └── Controllers/
│   │       ├── AuthController.php
│   │       ├── DrugSearchController.php
│   │       └── UserMedicationController.php
│   ├── Models/
│   │   ├── User.php
│   │   └── UserMedication.php
│   └── Services/
│       └── RxNormService.php
├── database/
│   ├── factories/
│   │   └── UserMedicationFactory.php
│   └── migrations/
├── routes/
│   └── api.php
├── tests/
│   ├── Feature/
│   │   ├── AuthTest.php
│   │   ├── DrugSearchTest.php
│   │   └── UserMedicationTest.php
│   └── Unit/
└── README.md
```

## API Integration

The application integrates with the following RxNorm API endpoints:

1. **getDrugs** - Search for drugs by name
   ```
   GET https://rxnav.nlm.nih.gov/REST/drugs.json?name={drug_name}
   ```

2. **getRxcuiHistoryStatus** - Get drug details including ingredients and dose forms
   ```
   GET https://rxnav.nlm.nih.gov/REST/rxcui/{rxcui}/historystatus.json
   ```

3. **getRxcuiStatus** - Validate RXCUI and check if active
   ```
   GET https://rxnav.nlm.nih.gov/REST/rxcui/{rxcui}/status.json
   ```

4. **getRxcuiProperties** - Get basic drug properties
   ```
   GET https://rxnav.nlm.nih.gov/REST/rxcui/{rxcui}/properties.json
   ```

## Error Handling

The API uses standard HTTP status codes:

- **200 OK** - Successful request
- **201 Created** - Resource successfully created
- **401 Unauthorized** - Missing or invalid authentication token
- **404 Not Found** - Resource not found
- **409 Conflict** - Resource already exists (duplicate)
- **422 Unprocessable Entity** - Validation errors
- **429 Too Many Requests** - Rate limit exceeded
- **500 Internal Server Error** - Server error

## Future Enhancements

- Add medication reminders
- Implement drug interaction checking
- Add medication history tracking
- Support for multiple medication lists (e.g., family members)
- Export medication list as PDF
- Integration with pharmacy systems

## Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## License

This project is open-source and available under the MIT License.

## Support

For issues, questions, or contributions, please create an issue in the repository.

## Acknowledgments

- [National Library of Medicine](https://www.nlm.nih.gov/) for providing the RxNorm API
- [Laravel](https://laravel.com/) for the excellent PHP framework
- Laravel Sanctum for seamless API authentication
