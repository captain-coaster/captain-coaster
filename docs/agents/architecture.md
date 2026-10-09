# Architecture notes

What the class names don't say. Entities: `src/Entity/`.

## Domain model

- **`User`**: `enabled` and `deletedAt` are part of the login-link and remember-me cookie signatures, so disabling or soft-deleting an account invalidates its magic links and cookies without deleting the row.
- **`Coaster`**: belongs to a `Park`, has `Image`s and a vocabulary taxonomy (`MaterialType`, `Model`, `Manufacturer`, `Launch`, `Restraint`, `SeatingType`, `Status`).
- **`RiddenCoaster`**: the ride record, one per `User` and `Coaster`. Its existence means the member has ridden the coaster. It holds a required rating (`value`, column `rating`, 0.5 to 5 by half steps), an optional review with its language, pros and cons `Tag`s, a computed `score` and an optional `riddenAt` date.
- **Coaster dates**: `openingDate` and `closingDate` are partial. Each has a `DatePrecision` (`day`, `month`, `year`) and the unknown part is stored as the 1st, so sorting and filtering read the date columns as before. They are written together, through `Coaster::setOpening()` / `setClosing()` and a `PartialDate` (`2026`, `2026-05` or `2026-05-17`), and displayed with the `partial_date` Twig filter. A ride date is accepted from `Coaster::getFirstRideDate()` (90 days before the opening, never before 1950) to `Coaster::getLastRideDate()` (the end of the closing period); `ValidRideDate` is in the `ride_date` validation group, checked only when a request changes the date.
- **`Ranking`** and **`RankingHistory`**: one `Ranking` per month and one `RankingHistory` row per ranked coaster, the source of truth.
  - `ranking:update` computes the ranking (`RankingCalculator`: pairwise duels from ratings and main Tops) and stages it unpublished (`publishedAt` null, invisible on the site).
  - `ranking:publish` publishes it on the 1st at noon UTC by copying the ranks into `Coaster::$rank` and `$previousRank`, the denormalized copy pages sort on.
  - `Ranking::$report` holds the run's report (top 10, newcomers, big moves, anomalies), posted to Discord.

## Database vocabularies

`App\Entity\Vocabulary` (country, continent, status, launch, restraint, tag, material and seating types) gives each value a stable `code` and an English `name`.

- Display one through `VocabularyLabeler`: `|vocab_label` in Twig, `|vocab_term` for a row carried as scalars. It translates the code and falls back to `name`, so a value added in the admin shows in English until its translation exists. It also sorts lists by translated label, which SQL can't.
- **Country**: `code` is the ISO 3166-1 alpha-2 code, labelled by Symfony Intl, with no translation file. `SYMFONY_INTL_WITH_USER_ASSIGNED` (`.env`) adds Kosovo (`XK`).
- **The others**: `code` is a key of the `database` translation domain (`status.operating`), and what PHP, CSS (`data-status`) and templates compare on. `DatabaseTranslationsTest` keeps the four locale files on the same keys.

## API

API Platform exposes read-only endpoints, shaped by serialization groups (`list_coaster`, `read_coaster`). `/api` and `/api/docs` are restricted to `ROLE_ADMIN` so no new external consumer signs up; existing API keys still work through `ApiKeyAuthenticator`, and the frontend calls the API itself (`search_controller.js`).

## Images

- Uploads go to S3 through `ImageManager` (Flysystem); `ImageListener` handles the entity lifecycle.
- Cropping and resizing happen outside this repo, in a Lambda of the sibling `captain-infra` project. This app only signs the request URLs with `PictureUrlSigner`.
- The canonical strings and the HMAC scheme must stay identical to captain-infra's `handler.mjs` (legacy layout) and `v2.mjs` (`/i/*` and `/a/*`). `PictureUrlSignerTest` holds the shared vectors.
- `PICTURES_V2` switches photos and avatars to the v2 layout together.
- The Lambda has no database access: per-image data the crop needs (the `watermarked` flag) travels as S3 object metadata set in `ImageManager::upload()`.

## Caching

Pick a cache's policy from what changes its data:

- **A known event** (ranking publication, a coaster, park, image or user edit): an explicit cache id cleared by the event's listener or subscriber (`src/EventListener/`, `RankingCacheSubscriber`), with a long TTL as a backstop. Example: `CoasterSummaryRepository`.
- **A continuous stream** (latest reviews, home stats): a short TTL, staleness accepted.
- **The current member's own actions**: uncached when the query is per member; when it is shared, a listener clears the entry so the member sees their action at once (`RiddenCoasterListener`).

Each piece of data lives in one cache layer. Search results are in `search.cache_pool` only, so a listener's clear takes effect immediately.

## Sitemaps

`/sitemap.xml` and `/sitemap_image.xml` are static files with no route: the daily `sitemap:update` cron writes them into `public/` (gitignored) and the web server serves them.

- A page type joins the sitemap in `SitemapEntries`, and its template sets `canonical` (`{route, params}`), which `base.html.twig` turns into the canonical URL and the `hreflang` alternates.
- `SitemapWriter` refuses a file above 50,000 URLs (the format's limit) and keeps the previous one. Past that, the next step is a sitemap index.

## Frontend build at deploy

On `main`, CI publishes `public/build` to GitHub Packages (`ghcr.io/captain-coaster/frontend-build`, tagged with the commit SHA). `deploy.sh` pulls that build with `oras` and runs Vite itself only when CI has none for the commit.
