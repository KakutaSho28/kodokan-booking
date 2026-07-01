# コーディングルール

## 共通

- 変更はタスクに必要な範囲へ絞る。
- ユーザーが作成した未コミット変更は勝手に戻さない。
- 既存の設計、命名、UI パターンに合わせる。
- 不要な大規模リファクタリングや整形差分を混ぜない。
- 日本語 UI 文言を使う。
- コメントを書く場合は日本語で、意図が分かりにくい箇所だけにする。
- 秘密情報、実パスワード、個人情報をコミットしない。

## フロントエンド

- Nuxt 3 / Vue 3 Composition API を使う。
- Vue ファイルは `<script setup lang="ts">` を基本とする。
- スタイリングは Tailwind CSS utility class を基本とする。
- UI はシンプルで業務システム向けにする。
- 患者向け画面と管理画面のトーンを混ぜない。
- モバイルファーストで実装する。
- タップターゲットはできるだけ `44px` 以上を確保する。
- API エラーはユーザーに分かる日本語メッセージで表示する。
- 型エラーを放置しない。最低限、以下を通す。

```bash
cd frontend
npm run typecheck
npm run build
```

## Nuxt / TypeScript

- `frontend/tsconfig.json` は Nuxt 生成の `.nuxt/tsconfig.json` を継承する。
- Nuxt の auto import 前提の API は、型解決のため `npm install` 後に `.nuxt` が生成されていることを確認する。
- `any` は避ける。外部 API レスポンスなど型が曖昧な場合は局所的に型定義を追加する。
- nullable な値は UI 表示側でフォールバックを用意する。

## バックエンド

- Laravel API として実装する。
- 既存 API のレスポンス互換性を壊さない。
- 入力検証は Controller 直書きより FormRequest が適切なら FormRequest を使う。
- 権限チェックを省略しない。
- 患者の予約可否は `patients.is_diagnosed` を正とする。
- 初診診断前の患者は予約不可。
- 予約作成 / 更新 / キャンセルなど重要操作は既存の監査ログ方針に合わせる。

## Docker / 環境

明示依頼がない限り、以下は変更しない。

- `docker-compose.yml`
- `Dockerfile`
- `entrypoint.sh`
- port mapping
  - frontend: `3001`
  - backend: `8000`

## 確認コマンド

フロントエンドのみ変更:

```bash
docker compose exec frontend npm run typecheck
docker compose exec frontend npm run build
```

バックエンドのみ変更:

```bash
docker compose exec backend php artisan test
```

全体確認:

```bash
curl http://127.0.0.1:8000/api/health
docker compose ps
```

