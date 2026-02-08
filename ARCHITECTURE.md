# Personalization API – Plugin Design & Logic

This document breaks down the plugin's structure, the logic behind my performance and security choices, and how the system would scale with more development time. Please keep in mind that this design is related to the second part of the assessement and not the initial architecture I provided for part one. The architecture diagram I made for part one deals with the ideal setup I would suggest and use if I was responsible for building this at Anaconda. Part two just shows you how I would put a plugin together in a limited amount of time. The constraint of making this something that doesn't require dev input to customize is not set for part two, therefore, its relatively static. It's a POC. Furthermore, in the topic of AI use, I use Cursor to quickly put boilerplate code together, organize things where they have to go, etc. It's a great tool to speed up what process of work you already know how to do.

---

## Why This Structure?

I used `personalization-api.php` as a single entry point—constants and includes load on `plugins_loaded`. That keeps activation predictable and makes it easy to turn the plugin off without leaving a mess. Cleanup is included.

Everything lives under the `Personalization_API` namespace. I split things into classes (Post_Meta, REST_API, Cache, etc.) and used singletons so it's clear what depends on what and the code stays testable. No magic.

For storage I went with post meta (`_personalization_*`) for industry, company size, and role. Editors stay on the post screen they already use; no new UX to learn. For now it's fairly static and can be customized later. I didn't add custom tables—WordPress already handles storage and backups, and the meta is registered with `register_post_meta()` so it's sanitized and ready if we ever want to expose it via REST elsewhere.

The API is one read-only endpoint, `GET /recommendations`. All the scoring and filtering lives in the REST_API class so the frontend has a single, simple integration point.

## Performance

I cache responses in transients, keyed by the attribute set (and pagination). TTL is 5 minutes so we get a balance between freshness and load. When you generate a new API key I flush the cache as a simple "clear all"—nothing fancy.

On the DB side I use one prepared query with a JOIN on post meta to get candidate post IDs. The actual scoring happens in PHP. I could do it in SQL but it gets messy and harder to change; keeping the match logic in one place in code made more sense for this POC.

Pagination is standard: `per_page` and `page`, and we only load what we need for the current request via `WP_Query`.

## Security

The endpoint expects a valid API key (checked with `hash_equals` to avoid timing issues) or a logged-in user with `read`. For now key generation is done in the admin UI—you hit a button and copy the key. Simple.

All request params go through `get_collection_params` with sanitize callbacks like `sanitize_text_field`. Saves use nonces and `current_user_can`; keys live in options, not in code, and after the first time we only show a masked version in the UI.

## What I Would Add With More Time

I'd invalidate cache only for keys affected by a post update instead of a full flush. I'd add an admin UI to define allowed values for industry/role/etc. so the API and meta boxes stay consistent. Click tracking is already partially there (record-click endpoint); I'd wire it so the Analytics tab gives real CTR without extra tooling. And I'd add rate limiting per key or IP so the API doesn't get hammered on EKS.

## Known Limitations

The match score is just the fraction of attributes that match—no weighting or boosting yet. Results are always ordered by score (best first). If there aren't enough strong matches to fill the page we pad with other targeted posts (lower or zero score) so the response is never empty; you still get a full list, just prioritized.

Impressions are recorded when the API returns; clicks only count if the client calls the record-click endpoint. So analytics is there but not fully automatic.

I like transients for this, they're ephemeral and simple. For real traffic on EKS I'd move to Redis so we're not leaning on the MySQL options table.

If the client sends no attributes we fall back to recent posts with any targeting. Real personalization means something else (front-end, CDP, etc.) has to send us profile data; this plugin just consumes it.