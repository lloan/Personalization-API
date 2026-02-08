# Personalization API – Quick reference

Get personalized content recommendations for your visitors based on their profile (industry, company size, role).

**Base URL:** `https://example.com/wp-json/personalization-api/v1`

---

## 1. Get recommendations

**What it does:** You send the visitor's attributes; the API returns a list of posts that match, with title, excerpt, link, and how well each post matches (0–100%).

**URL:** `GET /recommendations`

**You need an API key.** Create one under **Settings → Personalization API → API key** in WordPress. Add it to the request either in the URL as `?api_key=YOUR_KEY` or in a header: `X-API-Key: YOUR_KEY`.

**Parameters you can send:**

| Parameter       | What it is              | Example    |
|----------------|--------------------------|------------|
| `industry`     | Visitor's industry       | `technology`, `finance` |
| `company_size` | Size of their company    | `enterprise`, `smb`     |
| `role`         | Their job role           | `developer`, `manager`  |
| `per_page`     | How many posts to return | `10` (default), max 50  |
| `page`         | Page number for more results | `1`, `2`, …        |

**Example – open in browser (replace with your site and key):**

```
https://yoursite.com/wp-json/personalization-api/v1/recommendations?industry=technology&role=developer&per_page=5&api_key=YOUR_API_KEY
```

**Example response:**

```json
{
  "posts": [
    {
      "id": 42,
      "title": "Getting started with Python",
      "excerpt": "A short excerpt...",
      "url": "https://yoursite.com/getting-started-python/",
      "match_score": 1.0
    }
  ],
  "total": 15,
  "page": 1,
  "per_page": 10
}
```

`match_score` is 0.0 to 1.0 (e.g. 1.0 = all your attributes matched). Use the `url` to send the visitor to the article.

**Ordering:** Results are always sorted by match score, highest first. If there aren't enough posts that match your attributes to fill the page, the list is padded with other targeted content (lower or zero match score) so you still have something to show. Best matches always appear at the top.

---

## 2. Record a click (optional)

**What it does:** Tells the plugin that someone clicked a recommended post so Analytics can show click-through rates.

**URL:** `POST /record-click`  
**Body:** `{"post_id": 42}` (the post ID from the recommendations response)

Same API key as above. Success: `{"success": true}`.

---

## Notes

- **No attributes?** The API still returns recent posts that have targeting set; match scores will be 0.
- **Ordering:** Best matches first; remaining slots are filled with other targeted posts so you always get a full list.
- **401 error?** Check that your API key is correct and included.
- Results are cached for 5 minutes; new or updated content may take a few minutes to appear (trade-off).
