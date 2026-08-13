# Introduction

Renote REST API. All responses follow the {status, message, data} envelope.

<aside>
    <strong>Base URL</strong>: <code>http://localhost</code>
</aside>

    This documentation describes the Renote REST API consumed by clients (web, mobile).

    **Authentication**: Laravel Sanctum, bearer token. Obtain a token via `POST /api/register` or `POST /api/login`, then send `Authorization: Bearer {token}` on protected endpoints.

    **Response format**: every response (success or error) follows the envelope `{ "status": "success"|"error", "message": "...", "data": ... }`. Validation errors are nested under `data.errors`.

