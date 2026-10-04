# eventflow-backend

curl -X POST http://127.0.0.1:8000/api/v1/zones/update \
-H "Content-Type: application/json" \
-d '{"zoneId": "01a0e995-ee0c-730e-9b56-45c0d5c19ab8", "count": 2500}'

php artisan tinker
App\Models\Zone::pluck('id', 'name');