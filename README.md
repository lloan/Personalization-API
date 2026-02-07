# Personalization API

WordPress plugin that serves **personalized content recommendations** via a REST API (industry, company size, role) and an admin UI for tagging posts and viewing analytics.

**Requires:** WordPress 6.0+, PHP 7.4+

---

## Install

1. Upload the `personalization-api` folder to `wp-content/plugins/`, or zip it and use **Plugins → Add New → Upload**. Activate **Personalization API**.
2. **Settings → Personalization API → API key** — generate a key (needed for the API; shown once).
3. Edit any post — use the **Personalization targeting** meta box to set Industry, Company size, and Role.

---

## Usage

- **API:** `GET /wp-json/personalization-api/v1/recommendations?industry=...&company_size=...&role=...` (add `api_key=YOUR_KEY` or use header `X-API-Key`). See **API_DOCS.md** for details and examples.
- **Admin:** **Settings → Personalization API** — Audience (which posts target which attributes), Analytics (impressions/clicks), API key, Logs (when `WP_DEBUG` is on).

---

## Uninstall

Deactivate and delete the plugin. Data (options, post meta) remains unless you remove options prefixed `personalization_api_` and post meta `_personalization_industry`, `_personalization_company_size`, `_personalization_role`.

**License:** GPL v2 or later.
