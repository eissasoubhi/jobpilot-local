FROM node:22-bookworm-slim

ENV NODE_ENV=production \
    PLAYWRIGHT_BROWSERS_PATH=/ms-playwright

WORKDIR /app

COPY package.json ./

RUN npm install --omit=dev --no-audit --no-fund \
    && npx playwright install --with-deps chromium \
    && groupadd --gid 10001 jobpilot \
    && useradd --uid 10001 --gid 10001 --create-home --shell /usr/sbin/nologin jobpilot \
    && chown -R jobpilot:jobpilot /app /ms-playwright

COPY --chown=jobpilot:jobpilot src ./src

USER 10001:10001

EXPOSE 3100

CMD ["node", "src/server.mjs"]
