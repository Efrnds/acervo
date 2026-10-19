#!/bin/bash
set -euo pipefail

if [ "$(id -u)" -ne 0 ]; then
  echo "Roda com sudo: sudo bash scripts/enable-swap.sh" >&2
  exit 1
fi

if swapon --show | grep -q '^/swapfile'; then
  echo "Swap já está ativo."
  swapon --show
  free -h
  exit 0
fi

avail_kb="$(df -Pk / | awk 'NR==2 {print $4}')"
if [ "$avail_kb" -lt 2500000 ]; then
  echo "Menos de 2,5 GB livres em /. Não criei o swap." >&2
  exit 1
fi

dd if=/dev/zero of=/swapfile bs=1M count=2048 status=progress
chmod 600 /swapfile
mkswap /swapfile
swapon /swapfile
grep -q '^/swapfile ' /etc/fstab || echo '/swapfile none swap sw 0 0' >> /etc/fstab
printf 'vm.swappiness=10\n' > /etc/sysctl.d/99-swappiness.conf
sysctl -w vm.swappiness=10
echo "Swap de 2 GB ativo, swappiness 10."
free -h
