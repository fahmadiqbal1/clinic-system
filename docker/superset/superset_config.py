import os

SECRET_KEY = os.environ.get("SUPERSET_SECRET_KEY", "clinic-superset-insecure-dev-key-change-me")
SQLALCHEMY_DATABASE_URI = "sqlite:////app/superset_home/superset.db"
WTF_CSRF_ENABLED = False  # simplifies Docker init; re-enable behind a proxy in prod

# Allow iframe embedding from the Laravel app
HTTP_HEADERS = {"X-Frame-Options": "ALLOWALL"}
