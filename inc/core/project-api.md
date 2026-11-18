# Project management REST API

The private project board is available through the authenticated WordPress REST API at `/wp-json/zigurat-manager/v1/`. Requests require an authenticated WordPress manager account (administrator or approved contributor). Use HTTPS and a WordPress Application Password, or a logged-in session with a REST nonce. Never put credentials in source control or URLs.

**Current operating instruction:** do not use this API for project-card creation, edits, stage changes, or photo uploads unless the user explicitly authorizes API use again. Use the live project-management UI in the Codex in-app browser. Use the user's Windows Chromium session for browser-based execution-sheet rendering when needed, and verify the image cache before reporting success.

## Routes

| Method | Route | Purpose |
| --- | --- | --- |
| `GET` | `/projects` | List active project cards; supports `stage`, `search`, `page`, and `per_page` filters. |
| `POST` | `/projects` | Create a project card. |
| `GET` | `/projects/{id}` | Read one active project card. |
| `PATCH` | `/projects/{id}` | Update only the supplied project fields; omitted fields are preserved. |
| `GET` | `/collaborators` | List approved collaborators and IDs for work-item assignments. |
| `POST` | `/content-projects/draft` | Create a portfolio project draft with details, SEO metadata, and previously uploaded image IDs. Available to administrators and the dedicated `ziggurat_wp_agent` contributor. |
| `PATCH` | `/content-projects/draft/{id}` | Replace only the description using a JSON object with a single `content` field. The contributor can only edit its own drafts. |

Project fields are `title`, `client`, `phone`, `stage`, `followup`, `repeat_daily`, `work_items`, `proforma_ids`, `invoice_ids`, and `notes`. Stages are `lead`, `proforma`, `approval`, `production`, `delivery`, and `payment`. For `work_items`, each item has a `title`, `partner_ids` array, and optional `other_partner`.

Send up to two photos as multipart fields `project_photo` and `project_photo_2`. Each accepts JPG, PNG, or WebP up to 5 MB. Uploading a photo replaces only the corresponding slot. The saved record includes both slots in `photos` (empty slots are empty strings) and the first available photo in the legacy `photo` field.

## Examples

Create a card with JSON:

```sh
curl --user 'WORDPRESS_USER:APPLICATION_PASSWORD' \
  -H 'Content-Type: application/json' \
  -d '{"title":"تهران اینترنت - نیرو موتور - پیروزی","client":"نیرو موتور","stage":"production","notes":"پیروزی - جعبه بارانی ترانس - اس‌ام‌دی"}' \
  'https://example.com/wp-json/zigurat-manager/v1/projects'
```

Create or update a card with a photo:

```sh
curl --user 'WORDPRESS_USER:APPLICATION_PASSWORD' \
  -F 'title=بانک پارسیان - شعبه ظفر' \
  -F 'client=بانک پارسیان' \
  -F 'stage=production' \
  -F 'notes=ترانس هالوژن' \
  -F 'project_photo=@/path/to/zafar.jpg' \
  'https://example.com/wp-json/zigurat-manager/v1/projects'
```

Update a card while preserving omitted fields:

```sh
curl --user 'WORDPRESS_USER:APPLICATION_PASSWORD' \
  -X PATCH -H 'Content-Type: application/json' \
  -d '{"notes":"ترانس هالوژن"}' \
  'https://example.com/wp-json/zigurat-manager/v1/projects/123'
```

The API does not expose project deletion or permanent deletion. Payment stage requires a final invoice. Moving a project with an unconverted proforma directly to delivery returns `409`; convert and save the invoice first, then update the project stage.

## Rubika execution-sheet rendering for API writes

When a project created or changed through this API must send an execution-sheet image to a Rubika group, use the authenticated manager project board in the Codex in-app browser and verify that the image was cached before claiming success. Automatic polling for API-created cards is only present in Rubika plugin 1.8.0; until that package is installed on the live site, report clearly if the existing browser flow cannot render/cache the card. The live host's headless Chromium availability remains unverified. Group delivery runs server-side after the image is cached.

## Public project draft

Upload photos first through the authenticated WordPress media REST endpoint (`POST /wp-json/wp/v2/media`) and pass the resulting attachment IDs as `gallery`. The first gallery image becomes the featured image. Then create the draft:

```sh
curl --user 'WORDPRESS_USER:APPLICATION_PASSWORD' \
  -H 'Content-Type: application/json' \
  -d '{"title":"تابلو کامپوزیت و حروف چلنیوم بانک پارسیان، بلوار کشاورز","slug":"bank-parsian-keshavarz-sign","content":"توضیحات پروژه","excerpt":"خلاصه پروژه","project_city":"تهران","project_province":"تهران","project_neighborhood":"بلوار کشاورز","project_client":"بانک پارسیان","project_date":"1405/06/07","project_type":"تابلو کامپوزیت، حروف چلنیوم","zigurat_seo_title":"تابلو کامپوزیت بانک پارسیان در بلوار کشاورز | زیگورات","zigurat_seo_description":"اجرای تابلو کامپوزیت و حروف چلنیوم بانک پارسیان در بلوار کشاورز تهران توسط زیگورات.","zigurat_seo_focus_keyword":"تابلو کامپوزیت بانک پارسیان بلوار کشاورز","zigurat_seo_related_keywords":"تابلو چلنیوم تهران، تابلوسازی زیگورات","zigurat_seo_image_alt":"تابلو کامپوزیت و حروف چلنیوم بانک پارسیان در بلوار کشاورز تهران","gallery":[123,124]}' \
  'https://example.com/wp-json/zigurat-manager/v1/content-projects/draft'
```

This endpoint always creates a `draft`; it does not publish the project or post to social media. It validates the title, metadata and image attachment IDs, sets the first image as the featured image, and returns the editor URL. Publishing remains a separate user-approved action.
