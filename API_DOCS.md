# Personalization API – Endpoint Documentation

## Base URL

```
{site_url}/wp-json/personalization-api/v1
```

Example: `https://example.com/wp-json/personalization-api/v1`

---

## Recommendations

Returns a list of posts relevant to the given user attributes, with title, excerpt, URL, and match score.

### Endpoint

```
GET /recommendations
```

### Authentication

One of:

1. **API key** (for server-to-server or unauthenticated clients):
   - Header: `X-API-Key: your-api-key`
   - Or query: `?api_key=your-api-key`
   - Generate the key under **Settings → Personalization API → API key**.

2. **WordPress user** (cookie or Application Password):
   - If the request is made while logged in (e.g. cookie), or with [Application Passwords](https://developer.wordpress.org/rest-api/using-the-rest-api/authentication/#application-passwords), no API key is required.

Unauthenticated requests receive `401 Unauthorized`.

### Parameters

| Parameter     | Type    | Required | Default | Description |
|-------------|---------|----------|---------|-------------|
| `industry`  | string  | No       | —       | User's industry (e.g. `technology`, `finance`). |
| `company_size` | string | No    | —       | User's company size (e.g. `enterprise`, `smb`). |
| `role`      | string  | No       | —       | User's role (e.g. `developer`, `manager`). |
| `per_page`  | integer | No       | 10      | Number of posts to return (1–50). |
| `page`      | integer | No       | 1       | Page number for pagination. |

- Post meta can store **comma-separated** values; a match is when at least one value matches the user attribute (case-insensitive).
- If no attributes are sent, the API returns recent published posts that have at least one personalization attribute set.

### Response

**Success (200)**

```json
{
  "posts": [
    {
      "id": 42,
      "title": "Getting started with Python",
      "excerpt": "A short excerpt of the post...",
      "url": "https://example.com/getting-started-python/",
      "match_score": 1.0
    }
  ],
  "total": 15,
  "page": 1,
  "per_page": 10
}
```

- **match_score**: Float from `0.0` to `1.0`. Ratio of matching attributes (e.g. 2/3 attributes match → `0.67`). When no attributes are sent, score is `0`.

**Error (401)**

```json
{
  "code": "rest_forbidden",
  "message": "Invalid or missing API key.",
  "data": { "status": 401 }
}
```

### Example requests

**cURL (API key in header)**

```bash
curl -X GET "https://example.com/wp-json/personalization-api/v1/recommendations?industry=technology&role=developer&per_page=5" \
  -H "X-API-Key: YOUR_API_KEY"
```

**cURL (API key in query)**

```bash
curl "https://example.com/wp-json/personalization-api/v1/recommendations?industry=technology&company_size=enterprise&api_key=YOUR_API_KEY"
```

**JavaScript (fetch)**

```javascript
const params = new URLSearchParams({
  industry: 'technology',
  company_size: 'enterprise',
  role: 'developer',
  per_page: 10
});

const response = await fetch(
  `https://example.com/wp-json/personalization-api/v1/recommendations?${params}`,
  {
    headers: {
      'X-API-Key': 'YOUR_API_KEY'
    }
  }
);
const data = await response.json();
console.log(data.posts);
```

**PHP (wp_remote_get)**

```php
$url = add_query_arg( array(
  'industry'     => 'technology',
  'company_size' => 'enterprise',
  'per_page'     => 10,
), rest_url( 'personalization-api/v1/recommendations' ) );

$response = wp_remote_get( $url, array(
  'headers' => array(
    'X-API-Key' => get_option( 'personalization_api_key' ),
  ),
) );
$body = json_decode( wp_remote_retrieve_body( $response ), true );
```

### Caching

Responses are cached for 5 minutes (transient) per combination of attributes and pagination. Cache is cleared when a new API key is generated; post meta changes are reflected after the cache expires.

### Record click (optional)

To track when a user clicks a recommended post, call this endpoint so the Analytics tab can show CTR.

```
POST /record-click
```

**Body (JSON)** or **query/form:**

| Parameter | Type    | Required | Description |
|-----------|---------|----------|-------------|
| `post_id` | integer | Yes      | Post ID that was clicked. |

**Authentication:** Same as recommendations (API key or logged-in user).

**Example**

```bash
curl -X POST "https://example.com/wp-json/personalization-api/v1/record-click" \
  -H "X-API-Key: YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"post_id": 42}'
```

**Success (200):** `{"success": true}`  
**Error (400):** Invalid or non-published post.

---

### Errors and logging

- Invalid or missing auth → `401` and a warning is logged (when `WP_DEBUG` is on).
- Server errors are logged via the plugin logger; see **Settings → Personalization API → Logs** when `WP_DEBUG` is enabled.
