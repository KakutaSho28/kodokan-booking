# Kodokan Booking

講道館ビルクリニック向けのリハビリ予約システム試作です。

- Backend: Laravel
- Frontend: Nuxt3
- Database: SQLite

## ローカル起動

### Docker

```bash
docker compose up --build
```

Frontend: http://127.0.0.1:3001/
API: http://127.0.0.1:8000/api

初回起動時に Laravel の `.env` 作成、`APP_KEY` 生成、SQLite 作成、migration、seed を自動実行します。

停止:

```bash
docker compose down
```

### 直接起動

```bash
cd backend
php artisan migrate
php artisan db:seed
php artisan serve --host=127.0.0.1 --port=8000
```

```bash
cd frontend
npm install
npm run dev -- --port 3001
```

Frontend: http://127.0.0.1:3001/
API: http://127.0.0.1:8000/api

## デモアカウント

患者:

- 予約可能: `100001` / `1984-04-12`
- 初診で予約不可: `100002` / `1991-09-03`

スタッフ:

- `KB001` / `staffpass`

## 主な仕様

- 患者は診察券番号と生年月日でログイン
- スタッフはスタッフIDとパスワードでログイン
- 初診または医師のリハビリ許可がない患者は予約不可
- 患者は日付と担当者で枠を絞り込み、空き枠を予約
- 空き枠は `○`、満枠は `×` で表示
- スタッフは予約確認、枠変更、状態変更、キャンセル、メモ更新が可能
