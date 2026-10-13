FROM node:20-bookworm

# Fix permission error for Vite.
WORKDIR /home/node/app
RUN chown -R node /home/node/app

USER node

# Install dependencies a level up from the app; node traverses upwards to find node_modules.
WORKDIR /home/node
COPY --chown=node:node package.json package-lock.json ./
RUN npm ci

ENV NODE_PATH=/home/node/node_modules
ENV PATH=/home/node/node_modules/.bin:$PATH

WORKDIR /home/node/app
COPY --chown=node:node . .

EXPOSE 8080
CMD ["npm", "run", "dev"]
