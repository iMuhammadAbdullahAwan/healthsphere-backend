# Food Analysis & Logging API

The Food API allows users to analyze food images using AI and log their meals into a nutritional diary.

## Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/food-logs/analyze` | Analyze a food image without saving |
| GET | `/api/food-logs/recommendations` | Get meal recommendations based on history |
| POST | `/api/food-logs` | Log a meal into the database |
| GET | `/api/food-logs` | List food logs (with search and pagination) |
| GET | `/api/food-logs/summary` | Get nutrition summary |
| GET | `/api/food-logs/daily` | Get daily nutrition breakdown |
| GET | `/api/food-logs/{id}` | Get single food log detail |
| PUT | `/api/food-logs/{id}` | Update a food log entry |
| DELETE | `/api/food-logs/{id}` | Delete a food log entry |

## AI Analysis

### Analyze Food Image
`POST /api/food-logs/analyze`

Analyzes an image and returns nutritional data. This endpoint **does not** store the result in the database.

**Request Body (multipart/form-data):**
- `food_image`: Image file (JPEG, PNG, WebP)
- `analysis_type`: (optional) `openai`, `clarifai`, `gemini`, or `logmeal`

Uploaded food images are stored in `public/uploads/food/`, and the API returns a web-relative path in `image_path` (for example: `uploads/food/<filename>`).

**Response:**
```json
{
  "status": true,
  "message": "Food image analyzed successfully",
  "data": {
    "image_path": "uploads/food/random_name.jpg",
    "analysis": {
      "food_name": "Chicken Salad",
      "calories": 350,
      "protein": 25,
      "carbohydrates": 15,
      "fat": 12,
      "food_type": "prepared food",
      "food_family": "Meat",
      "nutritional_score": "A"
    },
    "analyzed_at": "2026-04-18 10:00:00"
  }
}
```

## Recommendations

### Get Meal Recommendations
`GET /api/food-logs/recommendations`

Returns personalized meal suggestions based on the user's past intakes and profile.

**Response:**
```json
{
  "status": true,
  "message": "Food recommendations retrieved successfully",
  "data": [
    {
      "name": "Grilled Salmon with Asparagus",
      "match_score": 95,
      "calories": 420
    }
  ]
}
```

## Logging

### Log a Meal
`POST /api/food-logs`

Permanently stores a meal in the user's food diary.

**Request Body (application/json):**
- `food_name`: (required) Name of the food
- `calories`: (required) Calorie count
- `meal_type`: (optional) breakfast, lunch, dinner, snack
- `protein`, `carbohydrates`, `fat`, etc.

**Response:**
```json
{
  "status": true,
  "message": "Meal logged successfully",
  "data": { "id": 123, ... }
}
```

## Listing

### Get Food Logs
`GET /api/food-logs`

Returns user's food diary. Supports searching by food name and filtering by meal type or date.

**Query Parameters:**
- `search`: (optional) Search by food name
- `meal_type`: (optional) breakfast, lunch, dinner, snack
- `start_date`: (optional) YYYY-MM-DD
- `end_date`: (optional) YYYY-MM-DD
- `page`: (optional) Page number
- `limit`: (optional) Items per page

**Response:**
```json
{
  "status": true,
  "message": "Food logs retrieved successfully",
  "data": {
    "logs": [
      { "id": 1, "food_name": "Chicken Salad", ... }
    ],
    "pagination": {
      "total": 50,
      "page": 1,
      "limit": 20,
      "pages": 3
    }
  }
}
```
