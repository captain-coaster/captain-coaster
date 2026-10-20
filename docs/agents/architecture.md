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
- Cropping and resizing happen outside this repo, in a Lambda of the sibling `captain-aws` project. This app only signs the request URLs with `PictureUrlSigner`.
- The canonical strings and the HMAC scheme must stay identical to captain-aws's `v2.mjs` (`/i/*` and `/a/*`). `PictureUrlSignerTest` holds the shared vectors.
- The Lambda has no database access: per-image data the crop needs (the `watermarked` flag) travels as S3 object metadata set in `ImageManager::upload()`.

## AI calls

Coaster summaries and the review and image moderation call OpenAI models on AWS Bedrock, through `Service/Ai/BedrockService`.

- `AiTask` sets each feature's model, reasoning effort and token budget; `AiModel` holds the Bedrock ids and prices. Moving a feature to another model is a change in `AiTask`.
- A call sends the fixed instructions as the system prompt, then the per-call input, so the shared prefix can hit Bedrock's implicit prompt cache (from 1,024 tokens). The answer is constrained by a strict JSON schema and comes back decoded.
- Every call, failed ones included, is logged as a row in `ai_call` with its tokens and cost.

## Moderation

Photos and review texts follow one model, before publication:

- Saving one dispatches `AnalyzeImageMessage` or `AnalyzeReviewMessage` to the async worker. Until the AI answers, it is hidden (`Image::enabled`, `RiddenCoaster::reviewEnabled`), and `analyzedAt` is empty. A review's author still sees their text, marked as under review; a rating without text is never held.
- Only a "nothing wrong" verdict at high confidence publishes it. Anything else, a failed analysis included once Messenger's retries are exhausted, keeps it hidden with a report (`ImageReport`, `ReviewReport`) for the admin queue, where approving publishes it.
- Reviews only, for now: a flag at high confidence hides the text without a moderator, except troll (humor and irony stay a moderator's call). Its report is resolved as `auto_hidden` (kept for spot checks, and a moderator can still approve it), and the author is told the review isn't published and can edit it, which sends it back to the AI.
- The AI never deletes nor bans: rejecting, clearing a review's text and banning are moderator actions.
- While its text waits for the AI or a moderator, its author cannot edit it: what gets published is always the text that was checked. Editing a published review sends it back to moderation.
- A member's report does not hide a review, but keeps it off the home page, and the AI does not publish an edited review while that report waits. Members can report a rating alone (fake ratings are most of their reports), not a text awaiting moderation.
- A review's text is read-only in the admin, and reports cannot be deleted: either would leave a review outside the queue.
- `app:analyze-reviews` sends reviews the worker never reached back to it.

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

The routes of the JavaScript router (`Routing.generate()`, routes marked `expose`) are part of that build: Vite dumps them with `fos:js-routing:dump` when it starts and `assets/js/routing.js` imports the JSON (gitignored). Nothing is fetched at runtime, so the build needs PHP and the vendors.
