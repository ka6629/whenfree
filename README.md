## 初回セットアップ

```
composer install
cp .env.example .env
php artisan key:generate
npm install
npm run build
php artisan migrate
```

## 動かすのに必要なもの
- PHP 8.4.1 以上
- Composer
- Node.js（LTS版）

## 最初に動かすとき
composer install
npm install
npm run build
php artisan migrate