FROM node:20-alpine

WORKDIR /app

# Copy dependency definition files
COPY package*.json ./

# Install packages
RUN npm install

# Copy source code
COPY . .

# Expose Vite development port
EXPOSE 5174

# Run Vite dev server, listening on all network interfaces
CMD ["npm", "run", "dev", "--", "--host"]
