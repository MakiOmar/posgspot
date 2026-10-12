# Build context: pos/storefront-qwik (git checkout on the VPS) — the shop is built here, never uploaded.
FROM node:22-alpine AS build

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY . .
# Uses the committed .env.production (PUBLIC_* values only)
RUN npm run build

FROM node:22-alpine

WORKDIR /app

COPY package.json package-lock.json ./
# Keep runtime deps used by the Express adapter (e.g. undici); omit only optional
RUN npm ci --omit=dev && npm install undici --no-save

COPY --from=build /app/dist ./dist
COPY --from=build /app/server ./server

ENV NODE_ENV=production
ENV PORT=3000
ENV ORIGIN=https://gamesspoteg.com

EXPOSE 3000
CMD ["node", "server/entry.express"]
