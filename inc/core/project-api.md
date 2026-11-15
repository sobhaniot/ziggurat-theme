# Project management REST API

The private project board is available through the authenticated WordPress REST API at `/wp-json/zigurat-manager/v1/`. Requests require an authenticated WordPress manager account (administrator or approved contributor). Use HTTPS and a WordPress Application Password, or a logged-in session with a REST nonce. Never put credentials in source control or URLs.

## Routes

| Method | Route | Purpose |
| --- | --- | --- |
| `GET` | `/projects` | List active project cards; supports `stage`, `search`, `page`, and `per_page` filters. |
| `POST` | `/projects` | Create a project card. |
| `GET` | `/projects/{id}` | Read one active project card. |
| `PATCH` | `/projects/{id}` | Update only the supplied project fields; omitted fields are preserved. |
| `GET` | `/collaborators` | List approved collaborators and IDs for work-item assignments. |

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
