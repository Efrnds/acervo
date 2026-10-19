# Acervo de prática

WordPress com o plugin Tainacan e o tema Tainacan Interface, pensado para a VM de 1 GB que já roda o OpenGym. O site publica na porta **8090**. As portas 80, 443 e 8080 continuam com o Caddy e o OpenGym.

A coleção **Gravuras do Século XX** já vem com metadados mapeados para Dublin Core, uma taxonomia de técnica, filtros na busca pública e seis obras fictícias. O roteiro de exercícios fica em `/roteiro/`.

## Na VM

O cron das 18h procura `/home/ubuntu/acervo/docker-compose.yml`. Copia esta pasta para lá.

```bash
sudo bash scripts/enable-swap.sh
bash scripts/init-env.sh http://IP_PUBLICO:8090
docker compose up -d
docker compose --profile init run --rm setup
```

`init-env.sh` mostra a senha da usuária `luiza` uma vez. O arquivo `.env` fica só na VM.

Na security list da Oracle, libera a entrada TCP **8090**. Restringe ao IP de casa se der.

Das 18h à meia-noite o `virada.sh` desliga este compose e sobe o OpenGym. O `docker compose start` da meia-noite sobe de novo o banco e o WordPress. O serviço `setup` tem profile `init` e não volta com eles.

## No notebook, para testar

```bash
bash scripts/init-env.sh http://localhost:8090
docker compose up -d
docker compose --profile init run --rm setup
```

Abre http://localhost:8090 e o painel em `/wp-admin`.
