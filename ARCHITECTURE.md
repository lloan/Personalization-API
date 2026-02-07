# Personalization API – Plugin Design & Logic

This document breaks down the plugin's structure, the logic behind my performance and security choices, and how the system would scale with more development time.

---

## Why This Structure?
* **Modular Bootstrap:** I’ve used `personalization-api.php` as a single entry point to define constants and load includes via `plugins_loaded`. This keeps activation clean and makes the plugin easy to toggle without side effects.
* **Clean Namespacing:** All code lives under the `Personalization_API` namespace. I’ve separated concerns into specific classes (Post_Meta, REST_API, Cache, etc.) using singletons to keep dependencies clear and the codebase testable.
* **Native Attribute Storage:** Target attributes like industry and company size are stored as post meta (`_personalization_*`). 
    * **Editor Familiarity:** Editors use the standard post screen they already know.
    * **No Custom Tables:** By staying native, WordPress handles all storage and backups automatically.
    * **REST Ready:** Meta is registered via `register_post_meta()` so it’s sanitized and ready for API exposure.
* **Streamlined REST API:** A single read-only endpoint (`GET /recommendations`) handles the heavy lifting. Logic for scoring and filtering is contained within the `REST_API` class to keep the integration point simple for the frontend.

## Performance Considerations
* **Smart Caching:** Responses are cached in transients keyed by specific attribute sets. I’ve set a 5-minute TTL to balance data freshness with server load. For a quick "clear all," the cache invalidates whenever a new API key is generated.
* **Optimized DB Usage:** I use a single prepared query with a JOIN on post meta to find candidates. I handle the actual scoring in PHP to avoid complex SQL that can bog down a database at scale. This keeps the matching logic maintainable.
* **Native Pagination:** The API supports `per_page` and `page` parameters, utilizing `WP_Query` to ensure we only load exactly what is needed for the current view.

## Security Measures
* **Robust Authentication:** The endpoint requires a valid API key (verified via `hash_equals` to prevent timing attacks) or a logged-in user with `read` capabilities.
* **Strict Input Handling:** All parameters are sanitized through `get_collection_params` using callbacks like `sanitize_text_field`. 
* **Admin Protections:** Every save operation uses nonces and `current_user_can` checks. API keys are stored in the options table (not code) and are masked in the UI after the initial generation.

---

## What I Would Add With More Time
* **Granular Cache Invalidation:** Instead of a full flush, I’d implement logic to only invalidate cache keys affected by specific post updates.
* **Validation UI:** An admin interface to define "allowed" values for industries or roles to ensure data consistency across the API and meta boxes.
* **Click Tracking:** A dedicated endpoint to record CTR (Click-Through Rate) so the Analytics tab reflects real-world performance without needing third-party tools.
* **Rate Limiting:** Implement per-IP or per-key limits to protect the EKS cluster from potential API abuse.

## Known Limitations
* **Matching Logic:** The current score is a simple fraction of matching attributes. There’s no complex weighting or "boosting" for specific content types yet.
* **Analytics Scope:** Impressions are tracked on API return, but clicks require an external call to the analytics logic.
* **Object Caching:** While transients work well, for high-traffic EKS setups, I’d prefer moving this to a dedicated **Redis** object cache to take the load off the MySQL options table.
* **The "Cold Start":** If a user provides no attributes, the system defaults to "Recent Posts." True personalization requires the client to feed the API profile data from a separate source.