# PR ルール

## ブランチ

- `main` から作業ブランチを切る。
- ブランチ名は内容が分かるようにする。

例:

```bash
git checkout main
git pull origin main
git checkout -b fix/frontend-ts-plugin
```

推奨 prefix:

- `feat/...`: 機能追加
- `fix/...`: バグ修正
- `chore/...`: 設定、依存、整理
- `docs/...`: ドキュメント

## コミット

- 1コミットに無関係な変更を混ぜない。
- 日本語でも英語でもよいが、何をしたか分かるメッセージにする。
- このプロジェクトでは日本語メッセージが多い。

例:

```bash
git commit -m "fix: Nuxtの型設定とts-pluginエラーを修正"
git commit -m "feat: 患者向けオンライン予約ポータル実装"
```

## PR 作成前チェック

変更内容に応じて実行する。

Frontend:

```bash
docker compose exec frontend npm install
docker compose exec frontend npm run typecheck
docker compose exec frontend npm run build
```

Backend:

```bash
docker compose exec backend php artisan test
```

共通:

```bash
git diff --check
git status --short
```

## PR 本文テンプレート

```markdown
## 概要
- 

## 変更内容
- 

## 確認
- [ ] `docker compose exec frontend npm run typecheck`
- [ ] `docker compose exec frontend npm run build`
- [ ] `docker compose exec backend php artisan test`

## 影響範囲
- 

## 備考
- 
```

不要なチェック項目は削除してよい。

## マージ方針

- 原則、PR を作成してから `main` にマージする。
- ユーザーが「マージして」と明示した場合のみ、PR 作成後にマージする。
- コンフリクトがある場合は、まず `main` の最新を取り込み、差分を確認してから解消する。
- CI やローカル検証が失敗している状態でマージしない。

## 禁止事項

- `main` へ直接作業コミットしない。
- 未確認の大規模整形を混ぜない。
- Docker の port mapping を勝手に変えない。
- 既存 API のエンドポイントやレスポンス形式を無断で壊さない。
- ユーザーの未コミット変更を勝手に破棄しない。

