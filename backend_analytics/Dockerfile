FROM python:3.12-slim

ENV PYTHONDONTWRITEBYTECODE=1 \
    PYTHONUNBUFFERED=1 \
    PYTHONPATH=/app

WORKDIR /app

RUN groupadd --gid 1000 hubapp \
    && useradd --uid 1000 --gid 1000 --shell /bin/bash --create-home hubapp

COPY requirements-ml.txt requirements.txt /app/
RUN pip install --no-cache-dir --upgrade pip \
    && pip install --no-cache-dir -r /app/requirements.txt

COPY . /app/backend_analytics/
RUN chown -R hubapp:hubapp /app

USER hubapp

EXPOSE 8001

CMD ["uvicorn", "backend_analytics.main:app", "--host", "0.0.0.0", "--port", "8001"]
