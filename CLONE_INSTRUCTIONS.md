# Repository Clone Instructions

## Repository URL
```
https://github.com/MakiOmar/posgspot.git
```

## Clone Options

### 1. Clone the entire repository (all branches)
```bash
git clone https://github.com/MakiOmar/posgspot.git
cd posgspot
```

### 2. Clone only the master branch (default)
```bash
git clone https://github.com/MakiOmar/posgspot.git
cd posgspot
```

### 3. Clone and checkout the Laravel upgrade branch directly
```bash
git clone -b feature/laravel-version-upgrade https://github.com/MakiOmar/posgspot.git
cd posgspot
```

### 4. Clone repository and then switch to the upgrade branch
```bash
git clone https://github.com/MakiOmar/posgspot.git
cd posgspot
git checkout feature/laravel-version-upgrade
```

## Current Branch Information

**Branch Name:** `feature/laravel-version-upgrade`

**Latest Commits:**
- Fix duplicate routes and update to modern class-based syntax
- Remove outdated TODO comment - edit log already implemented
- Fix current stock calculation to include stock transferred from other locations
- Enable reference number and edit expiry date button in stock expiry report
- Uncomment button toggle functionality in Repair job sheet views
- Optimize purchase line decrement query by grouping by purchase_line_id
- Remove unused sidebar from RepairServiceProvider view composer
- Remove unused view composer from WoocommerceServiceProvider
- Update Laravel auth controllers and service providers for version upgrade

## After Cloning

### Install Dependencies
```bash
composer install
npm install  # if using npm
```

### Environment Setup
```bash
cp .env.example .env
php artisan key:generate
```

### Database Setup
```bash
php artisan migrate
php artisan db:seed  # if needed
```

## Branch Status

The `feature/laravel-version-upgrade` branch is up to date with remote and contains all the latest improvements and fixes.

