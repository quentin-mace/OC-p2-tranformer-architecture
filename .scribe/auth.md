# Authenticating requests

To authenticate requests, include an **`Authorization`** header with the value **`"Bearer {YOUR_TOKEN}"`**.

All authenticated endpoints are marked with a `requires authentication` badge in the documentation below.

Retrieve a token via `POST /api/register` or `POST /api/login`. Then send `Authorization: Bearer {token}` on every request.
