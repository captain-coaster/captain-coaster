# Captain Coaster

A participative guide for roller coaster enthusiasts: rate, review and build Top lists of the coasters you have ridden, and help shape a world ranking computed every month from members' ratings and Tops.

Built with Symfony 8 (PHP 8.5), MariaDB, Redis, Twig Components, Stimulus and Tailwind CSS v4 (Vite).

## Installation

### Option 1: Symfony CLI (recommended)

Requirements: PHP 8.5, [Composer](https://getcomposer.org/download/), Node.js, Docker and the [Symfony CLI](https://symfony.com/download).

1. Clone the project and install its dependencies
    ```shell
    composer install
    npm install
    ```
2. Start the services: MariaDB 11.8, Redis, and Adminer on http://localhost:8081
    ```shell
    docker compose up -d
    ```
3. Point the app at the database, in a new `.env.local`
    ```dotenv
    DATABASE_URL="mysql://root:root123@127.0.0.1:3306/captain?serverVersion=11.8.0-MariaDB&charset=utf8mb4"
    ```
4. Create the database, build the schema and load a small set of sample data
    ```shell
    php bin/console doctrine:database:create --if-not-exists
    composer db-setup
    php bin/console doctrine:fixtures:load
    ```
5. Start the Symfony server; it starts the Vite dev server too (`.symfony.local.yaml`)
    ```shell
    symfony server:start -d
    ```
6. Browse the URL printed by the Symfony CLI (typically https://127.0.0.1:8000). `symfony server:stop` stops both servers.

To test on a phone on the same network, build the assets and let the server listen on the network:

```shell
symfony server:stop && npm run build
symfony server:start -d --allow-all-ip --no-workers
```

Then open `https://<your computer's IP>:<port>` on the phone and accept the certificate warning. Restart with a plain `symfony server:start -d` to get the dev server and hot reload back.

### Option 2: full Docker setup

`docker-compose.full.yml` includes the services above and adds nginx (http://localhost:8080) and PHP 8.5.

1. Build the frontend on the host (there is no Node container)
    ```shell
    npm install && npm run build
    ```
2. Build and start the containers
    ```shell
    docker compose -f docker-compose.full.yml up --build -d
    ```
3. Set `DATABASE_URL` in `.env.local` as in option 1, with `db` as the host instead of `127.0.0.1`
4. Install the PHP dependencies, then create the database and its sample data
    ```shell
    docker exec -ti php-captain composer install
    docker exec -ti php-captain php bin/console doctrine:database:create --if-not-exists
    docker exec -ti php-captain composer db-setup
    docker exec -ti php-captain php bin/console doctrine:fixtures:load
    ```
5. Browse http://localhost:8080

## Checks

CI runs these on every pull request; run them before pushing:

```shell
vendor/bin/phpunit
vendor/bin/phpstan analyse
vendor/bin/php-cs-fixer fix
npm run check:css-contract
npm run check:icon-sets
```

## Contributing

1. Fork the repository and create a branch
2. Keep the pull request small: one feature or fix
3. Give it a [Conventional Commits](https://www.conventionalcommits.org/) title (`feat(ranking): …`, `fix(search): …`); CI checks it

Project conventions are in [`AGENTS.md`](AGENTS.md), the design system in [`DESIGN.md`](DESIGN.md).

## License

MIT.
