# Claude Code 作業ガイド

このリポジトリは、講道館ビルクリニック向けのリハビリ予約システムです。作業を始める前に以下を必ず確認してください。

- 引き継ぎ書: [docs/HANDOFF.md](docs/HANDOFF.md)
- コーディングルール: [docs/CODING_RULES.md](docs/CODING_RULES.md)
- PR ルール: [docs/PR_RULES.md](docs/PR_RULES.md)

## 最初に確認すること

```bash
git status --short
git branch --show-current
docker compose ps
```

作業前に未コミット差分がある場合は、ユーザー作業の可能性があるため勝手に戻さないでください。

## 基本コマンド

```bash
docker compose up -d
curl http://127.0.0.1:8000/api/health
docker compose exec frontend npm run typecheck
docker compose exec frontend npm run build
docker compose exec backend php artisan test
```

## 重要な制約

- `docker-compose.yml`、`Dockerfile`、`entrypoint.sh`、ポート番号は明示依頼がない限り変更しない。
- バックエンド API の互換性を壊さない。
- 患者の初診制御は `is_diagnosed` のみを使う。`status: new` などの別表現は禁止。
- フロントエンドは Nuxt 3 / Vue 3 Composition API / Tailwind CSS を基本とする。
- バックエンドは Laravel 10 系 API として扱う。

