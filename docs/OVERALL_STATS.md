# Overall Stats API

The Overall Stats API provides a single endpoint to retrieve aggregated statistics across all HealthSphere modules for the authenticated user. This includes data from Scheduler, Device Guider, Food Lens, Steps, and Therapist modules.

## Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/overall-stats` | Retrieve aggregated statistics for all modules |

---

## Get Overall Stats
`GET /api/overall-stats`

Returns an aggregated JSON object containing statistics for all modules.

**Authentication:** 
Requires a valid Bearer token (JWT).

**Request Headers:**
- `Authorization`: `Bearer <token>`

**Response Structure:**
- `scheduler`: Statistics related to scheduled routines, adherence, streaks, and module-specific averages (medicine, water, sleep).
- `device_guider`: Statistics related to device readings and biomarkers.
- `food_lens`: Statistics related to food logs, macronutrients, and calories.
- `steps`: Statistics related to pedometer sessions and goals.
- `therapist`: Statistics related to therapeutic exercise logs.

**Response Example:**
```json
{
  "status": true,
  "message": "Overall stats retrieved successfully",
  "data": {
    "scheduler": {
      "total_active_routines": 4,
      "overall_adherence_rate_percent": 85.5,
      "current_streak_days": 12,
      "medicine_adherence_percent": 100,
      "average_water_intake_ml": 2500,
      "average_sleep_duration_hours": 7.5
    },
    "device_guider": {
      "total_lifetime_scans": 45,
      "device_usage_breakdown": {
        "GlucoLab": 30,
        "Blood Pressure Monitor": 15
      },
      "biomarker_stability_percent": 90.5,
      "recent_abnormalities_30d": 2
    },
    "food_lens": {
      "total_meals_logged": 120,
      "daily_caloric_average": 2100.5,
      "macronutrient_averages": {
        "protein": 110.5,
        "carbohydrates": 250.0,
        "fat": 65.2
      },
      "meal_consistency_per_day": 2.8
    },
    "steps": {
      "total_lifetime_steps": 250000,
      "total_distance_km": 190.5,
      "total_calories_burned": 8500.5,
      "goal_achievement_rate_percent": 75.0
    },
    "therapist": {
      "total_exercises_done": 30,
      "total_time_invested_minutes": 450,
      "calories_burned": 3200.5,
      "top_exercise": "Neck Stretches"
    }
  }
}
```

### Metrics Definition

**Scheduler**
- `total_active_routines`: Number of schedules currently set to "active".
- `overall_adherence_rate_percent`: Percentage of total logs marked "completed" vs "missed" / "skipped" / "canceled".
- `current_streak_days`: Number of consecutive days with at least one completed schedule.
- `medicine_adherence_percent`: Completion rate specifically for `schedule_type: "medicine"`.
- `average_water_intake_ml`: Daily average calculated from completed water schedules.
- `average_sleep_duration_hours`: Average hours slept based on completed sleep schedules.

**Device Guider**
- `total_lifetime_scans`: Total count of all readings ever recorded.
- `device_usage_breakdown`: Count of scans grouped by device name.
- `biomarker_stability_percent`: Percentage of readings marked as "normal" vs "high"/"low".
- `recent_abnormalities_30d`: Count of "high" or "low" readings in the last 30 days.

**Food Lens**
- `total_meals_logged`: Total count of images/meals analyzed and saved.
- `daily_caloric_average`: Average of total calories consumed per day over logged days.
- `macronutrient_averages`: Daily average breakdown of protein, carbohydrates, and fat.
- `meal_consistency_per_day`: Average number of logs per active day.

**Steps**
- `total_lifetime_steps`: Sum of steps across all sessions.
- `total_distance_km`: Sum of distance across all sessions.
- `total_calories_burned`: Sum of calories from step sessions.
- `goal_achievement_rate_percent`: Percentage of days the user met their `daily_step_goal`.

**Therapist**
- `total_exercises_done`: Total count of recorded exercise sessions.
- `total_time_invested_minutes`: Sum of minutes spent on exercises.
- `calories_burned`: Total calories burned from therapeutic exercises.
- `top_exercise`: The most frequently performed exercise.
