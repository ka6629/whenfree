# whenfree

チームのみんなの「空いている時間」を重ねて見て，集まる日時を決めるための Web アプリです．
各自が予定を入れておくと，チームの1週間の空き・埋まりが表で分かり，候補の日時に「はい・いいえ」で返事を集められます．

**公開した URL：https://whenfree-production-q284kb.laravel.cloud**

## できること

- **自分の予定**：毎週の予定（授業・バイトなど）と単発の予定を登録します．変わっていなければ「予定は変わっていません」を押すだけで，確かめたことになります．
- **チーム**：チームを作り，招待コードを伝えてメンバーに参加してもらいます．
- **チームの1週間**：全員の予定を重ねて，1時間ごとに表で出します．マスを押すと，誰が空いているかを見て，そのまま候補を作れます．
  - ○：全員が空いている／△2/4：一部の人が空いている（空いている人数／全員）／×：全員に予定がある／？3/4：予定を確かめていない人がいる
  - 毎週日曜 21:00 の区切りの後に予定を確かめていない人は「不明」になります．古い予定のまま日時を決めてしまうのを防ぐためです．
- **候補と返事**：候補の日時に，メンバーが「はい・いいえ」で返事をします．「あと◯人の返事待ち」が分かり，候補を作った人が確定・取り消しをします．
- **Google カレンダーの読み込み**：つなぐと，予定が入っている時間を「埋まり」にします．読むのは空き・埋まりの時間だけで，予定の名前などの中身は読みません．

## 使い方

1. 公開した URL の `/register` を開いて，アカウントを作ります．
2. 「チーム」の画面で，チームを作るか，招待コードを入れて参加します．
3. 「予定」の画面で予定を入れます．週に1回，予定を確かめます．
4. チームの画面から「チームの1週間」を開き，マスを押して候補を作ります．
5. メンバーが「はい・いいえ」で返事をし，候補を作った人が確定します．

### Google カレンダーとつなぐとき（なくても使えます）

Google の審査を受けていないので，つなぐときに「このアプリは Google で確認されていません」と出ます．
「詳細」→「whenfree-production-q284kb.laravel.cloud（安全ではないページ）に移動」で進めてください．

- 情報の扱いは [プライバシーポリシー](https://docs.google.com/document/d/e/2PACX-1vQTS_NkVVHkYKKDkVeehfD9mohja88iVu8-7up2jxT_G2Y_sKNog-C4ks0fCGtEN-a6OHGSVFDIoe7J/pub) に書いています．
- つながりは，アプリの「Google カレンダー」の画面からいつでも外せます．

## 気づいた点を送る

使いにくい所・バグ・コードへのレビューなどは，[GitHub の Issue](https://github.com/ka6629/whenfree/issues) に書いてもらえるとうれしいです．
どの画面で，何をしたら，どうなったかを書いてもらえると助かります．

## 手元で動かす

必要なもの：PHP 8.4.1 以上，Composer，Node.js（LTS 版）

```sh
git clone https://github.com/ka6629/whenfree.git
cd whenfree
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
npm install
npm run build
composer run dev
```

ブラウザで http://localhost:8000 を開きます．データベースは SQLite（`database/database.sqlite`）です．

- テスト：`php artisan test`
- データを最初の状態に戻す：`php artisan migrate:fresh --seed`
- Google カレンダーを試すとき：Google Cloud でクライアントを作り，`.env` の `GOOGLE_CLIENT_ID`・`GOOGLE_CLIENT_SECRET` に値を入れて，`php artisan config:clear` を実行します．くわしくは [#61](https://github.com/ka6629/whenfree/pull/61) の「確認方法」を見てください．

## 公開の手順

[Laravel Cloud](https://cloud.laravel.com) で公開しています．`main` に取り込むと，自動でデプロイされます（Push to deploy）．

### 公開し直すとき

1. develop から `main` への Pull Request を出し，レビューを受けて取り込みます．
2. Laravel Cloud の Deployments で，デプロイが「Deployed」になったことを確かめます．

### Laravel Cloud の設定

| 項目 | 値 |
| --- | --- |
| 地域 | Tokyo（アプリとデータベースの両方） |
| データベース | Laravel MySQL 8.4 |
| ブランチ | `main` |
| PHP | 8.5 |
| ビルドのコマンド | `composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader`<br>`npm ci --audit false`<br>`npm run build` |
| デプロイのコマンド | `php artisan migrate --force` |
| 環境変数 | `APP_ENV=production`・`APP_DEBUG=false`・`APP_URL`・`GOOGLE_CLIENT_ID`・`GOOGLE_CLIENT_SECRET` など（値は Laravel Cloud の画面だけに入れ，リポジトリには入れません） |

### Google Cloud の設定

- 「承認済みのリダイレクト URI」に `https://whenfree-production-q284kb.laravel.cloud/google-calendar/callback` を入れます（`APP_URL` に `/google-calendar/callback` を付けた形と，1文字も違わないようにします）．
- 同意画面は「本番環境」にしています．審査は受けていないので，使える人数の上限は 100 人です．
- ロゴは上げていません．上げると Google の審査が必要になるためです．
