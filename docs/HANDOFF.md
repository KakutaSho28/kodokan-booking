# 引き継ぎ書

## プロジェクト概要

講道館ビルクリニック向けの整形外科リハビリ予約システムです。

- Frontend: Nuxt 3 / Vue 3 / TypeScript / Tailwind CSS
- Backend: Laravel API / PHP
- DB: Docker Compose 環境では SQLite 設定。要件上は MySQL 想定の履歴あり。
- 認証: 独自 AccessToken + Laravel 側 `/api/auth/*`
- 患者: 診察券番号 + 生年月日でログイン
- スタッフ: スタッフID + パスワードでログイン

## ローカル環境

```bash
cd /Users/sho/Documents/Kodokan-Booking
docker compose up -d
```

URL:

- Frontend: http://127.0.0.1:3001
- Backend API: http://127.0.0.1:8000/api
- Health check: http://127.0.0.1:8000/api/health

よく使うコマンド:

```bash
docker compose ps
docker compose exec frontend npm install
docker compose exec frontend npm run typecheck
docker compose exec frontend npm run build
docker compose exec backend php artisan migrate
docker compose exec backend php artisan test
```

## 現在の状態

2026-07-01 時点で、ログアウト機能の PR は main にマージ済みです。

- PR: https://github.com/KakutaSho28/kodokan-booking/pull/6
- main のマージ済みコミット: `7feff91`

その後、VSCode の ts-plugin エラー対策として以下を実装済みです。

- 作業ブランチ: `fix/frontend-ts-plugin`
- コミット: `795ff13 fix: Nuxtの型設定とts-pluginエラーを修正`
- 内容:
  - `frontend/tsconfig.json` を追加
  - `frontend/package.json` に `typecheck` script を追加
  - `typescript` / `vue-tsc` / `@types/node` を devDependency に追加
  - `frontend/pages/index.vue` の nullable 型エラーを修正

このブランチは、必要に応じて push / PR 作成してください。

## デモアカウント

患者ログイン:

| 種別 | 診察券番号 | 生年月日 | 備考 |
| --- | --- | --- | --- |
| 診断済み | `100001` | `1984-04-12` | 予約可能 |
| 未診断 | `100002` | `1991-09-03` | オンライン予約不可 |
| 診断済み | `100003` | `1976-12-20` | 予約可能 |

スタッフログイン:

| 種別 | スタッフID | パスワード |
| --- | --- | --- |
| 管理者 | `KB001` | `staffpass` |
| スタッフ | `PT001` | `staffpass` |
| スタッフ | `PT002` | `staffpass` |
| スタッフ | `PT003` | `staffpass` |

## 実装済みの主な機能

- 患者ポータル
  - 患者ログイン
  - 担当者選択
  - 空き枠 `○` / 満枠 `×` 表示
  - 予約作成
  - マイページ
  - 予約キャンセル
  - 利用規約 / プライバシーポリシー
- 管理画面
  - スタッフログイン
  - ダッシュボード
  - 予約管理
  - 患者管理
  - スタッフ / シフト管理
  - ログアウト
- バックエンド
  - 予約 CRUD
  - 空き枠 API
  - 患者管理
  - 待機リスト
  - メール通知基盤
  - セキュリティ強化

## 注意点

- `frontend/tsconfig.json` は `.nuxt/tsconfig.json` を extends しています。VSCode で赤線が出る場合は、`frontend` で `npm install` 後に以下を実行してください。

```bash
npm run typecheck
```

VSCode 側では以下も実行してください。

- `TypeScript: Restart TS Server`
- `Vue: Restart Vue Server`

- Docker の frontend コンテナは volume で `frontend_node_modules` を使います。ローカルとコンテナの `node_modules` は別物なので、両方で確認する場合はそれぞれ `npm install` が必要です。
- 既存の `frontend/pages/index.vue` は古い統合デモ画面の性格が残っています。患者ポータルの主導線は `/portal/login`、管理画面は `/admin` です。

