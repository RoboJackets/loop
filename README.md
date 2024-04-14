# loop
Tracking, auditing, and reconciling SOFO forms

> [!WARNING]
> While this repository itself is open-source, we use several **confidential and proprietary** components which are packed into Docker images produced by this process. Images should **never** be pushed to a public registry.

Install Docker and Docker Compose.

Clone the repository, then run

```sh
export DOCKER_BUILDKIT=1
docker build --pull --target backend-uncompressed --network host --secret id=composer_auth,src=auth.json . --tag robojackets/loop
docker compose up
```

You will need to provide an `auth.json` file that has credentials for downloading Laravel Nova. Ask in Slack and we can provide this file to you.
