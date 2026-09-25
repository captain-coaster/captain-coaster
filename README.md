# Captain Coaster

## About us

Captain Coaster is the ultimate guide for roller coaster enthusiasts!
Rate, write reviews, and craft top lists for the coasters you've ridden.
Join us in shaping the world's best roller coaster rankings!

## Installation

### Option 1: Local Development with Symfony CLI

1. Clone the project
2. Install [Symfony CLI](https://symfony.com/download)
3. Install PHP 8.5 locally
4. Install [Composer](https://getcomposer.org/download/) locally
5. Install Composer dependencies
    ```shell
    composer install
    ```
6. Install Node dependencies
    ```shell
    npm install
    ```
7. Start the database services using Docker
    ```shell
    docker-compose up -d
    ```
    This will start:
    - MariaDB 11.8
    - Redis
    - Adminer on localhost:8081
8. Build the schema and load a small set of sample data
    ```shell
    composer db-setup
    php bin/console doctrine:fixtures:load
    ```
9. Start the Symfony server; it starts the Vite dev server too (`.symfony.local.yaml`)
    ```shell
    symfony server:start -d
    ```
10. Browse the application at the URL provided by Symfony CLI (typically https://127.0.0.1:8000). Stop both with `symfony server:stop`.

To test on a phone on the same network, build the assets and let the server listen on the network:
```shell
symfony server:stop && npm run build
symfony server:start -d --allow-all-ip --no-workers
```
Then open `https://<your computer's IP>:8000` on the phone and accept the certificate warning. Restart with a plain `symfony server:start -d` to get the dev server and hot reload back.

### Option 2: Full Docker Setup

1. Clone the project
2. Build and start all containers using the full Docker Compose configuration
    ```shell
    docker compose -f docker-compose.full.yml up --build -d
    ```
    Containers provided:
    - nginx on localhost:8080
    - PHP 8.5
    - MariaDB 11.8
    - Redis
    - Adminer on localhost:8081
3. Install composer dependencies
    ```shell
    docker exec -ti php-captain composer install
    ```
4. Create a `captain` database on adminer, then build the schema and load a small set of sample data:
    ```shell
    docker exec -ti php-captain composer db-setup
    docker exec -ti php-captain php bin/console doctrine:fixtures:load
    ```
5. Browse `localhost:8080`

## Docker Compose Structure

The project uses a modular Docker Compose setup:

- `docker-compose.yml` - Base configuration with database services (MariaDB, Redis, Adminer)
- `docker-compose.full.yml` - Imports the base configuration and adds web services (nginx, PHP, Node)

## Worktrees

Each worktree gets its own database, cloned from `captain`, and its own
generated `.env.dev.local`. See `AGENTS.md` and the `dev-environment` skill
for the setup procedure.

## Contributing

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/my-new-feature`
3. Commit your changes: `git commit -am 'Add some feature'`
4. Push to the branch: `git push origin feature/my-new-feature`
5. Submit a pull request

## License

This project is licensed under the MIT License - see the LICENSE file for details.
