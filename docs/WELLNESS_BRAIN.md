# Wellness Brain (Health Intelligence API)

The Wellness Brain is the central intelligence layer of HealthSphere. It aggregates data from across the platform to provide a holistic assessment of the user's health.

## Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/wellness/status` | Get real-time health score, domain breakdown, and AI insights |
| GET | `/api/wellness/history` | Get historical health scores for trend analysis |

---

## Wellness Status Breakdown

The system calculates an **Overall Score (0-100)** based on four professional domains:

### 1. Metabolic Integrity (30%)
- **Source**: Vitals logged via the Device Guider.
- **Criteria**: Stability of readings (Normal vs High/Low) over the last 7 days.

### 2. Routine Adherence (30%)
- **Source**: Medication and Task schedules.
- **Criteria**: Completion rate of scheduled logs.

### 3. Nutritional Balance (20%)
- **Source**: Food diary.
- **Criteria**: Regularity of meal logging (Goal: 3 meals per day).

### 4. Activity & Vigor (20%)
- **Source**: Steps and Exercise logs.
- **Criteria**: Step goal achievement and workout frequency (Goal: 3+ sessions/week).

---

## Health Status Levels

| Status | Score Range | Description |
|--------|-------------|-------------|
| **Optimal** | 80 - 100 | Excellent health management. |
| **Fair** | 60 - 79 | Good, but minor inconsistencies detected. |
| **Guarded** | 40 - 59 | Significant gaps in adherence or vitals. |
| **Critical** | < 40 | Urgent attention needed. |

---

## Example Response (`GET /api/wellness/status`)

```json
{
  "status": true,
  "message": "Wellness intelligence retrieved successfully",
  "data": {
    "overall_score": 85,
    "health_status": "optimal",
    "trend": "improving",
    "ai_advisor": "Your health intelligence is at an optimal level. You are maintaining excellent consistency...",
    "domains": {
      "metabolic": { "score": 90, "label": "Metabolic Integrity", ... },
      "routine": { "score": 85, "label": "Routine Adherence", ... },
      "nutrition": { "score": 75, "label": "Nutritional Balance", ... },
      "activity": { "score": 88, "label": "Activity & Vigor", ... }
    },
    "updated_at": "2026-04-25 12:45:00"
  }
}
```
