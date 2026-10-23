# Translations

Four locales, `en`, `fr`, `es`, `de`, in `translations/{domain}+intl-icu.{locale}.yml`. A key exists in all four or in none.

- A major feature area has its own domain (`home`, `profile`, `ranking`, `top`); `messages` holds the shared rest.
- Keys are `snake_case`. A form's strings: `{feature}.form.{field}`, `{field}_help`, `{field}_placeholder`, `submit`, `submitting`. Its flashes: `{feature}.flash.*`. Strings shared by several forms: `form.*`. A feature uses its own keys, never another feature's.
- Exception: `filters.{name}` follows the filter's query parameter name.
- Wording follows the Voice section of `DESIGN.md`.
