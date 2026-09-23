FROM node:20-alpine

WORKDIR /app

COPY package.json package-lock.json ./
# Keep runtime deps used by the Express adapter (e.g. undici); omit only optional
RUN npm ci --omit=dev && npm install undici --no-save

COPY dist ./dist
COPY server ./server

ENV NODE_ENV=production
ENV PORT=3000
ENV ORIGIN=https://thespotmanagment.io

EXPOSE 3000
CMD ["node", "server/entry.express"]
