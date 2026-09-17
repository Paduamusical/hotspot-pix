#!/bin/bash
export $(grep -v '^#' /var/www/hotspot-pix/.env | xargs)
BASE="$MIKROTIK_REST_URL"; AUTH="$MIKROTIK_USER:$MIKROTIK_PASS"
DOMAINS="caixa.gov.br internetbanking.caixa.gov.br api.caixa.gov.br app.caixa.gov.br mobile.caixa.gov.br caixa.com.br bancopan.com.br api.bancopan.com.br app.bancopan.com.br mobile.bancopan.com.br pan.com.br carteirapan.com.br nubank.com.br api.nubank.com.br app.nubank.com.br bradesco.com.br api.bradesco.com.br app.bradesco.com.br bradescard.com.br itau.com.br api.itau.com.br app.itau.com.br mobile.itau.com.br bb.com.br api.bb.com.br app.bb.com.br mobile.bb.com.br santander.com.br api.santander.com.br app.santander.com.br mobile.santander.com.br mercadopago.com.br api.mercadopago.com bancointer.com.br api.bancointer.com.br app.bancointer.com.br c6bank.com.br api.c6bank.com.br app.c6bank.com.br bv.com.br api.bv.com.br app.bv.com.br sicredi.com.br api.sicredi.com.br app.sicredi.com.br sicoob.com.br api.sicoob.com.br app.sicoob.com.br banrisul.com.br api.banrisul.com.br app.banrisul.com.br picpay.com api.picpay.com pagseguro.com.br paypal.com stone.com.br btgpactual.com bancooriginal.com.br digio.com.br pagbank.com.br recargapay.com.br connectivitycheck.gstatic.com connectivitycheck.android.com captive.apple.com www.msftconnecttest.com"
curl -s -u "$AUTH" "$BASE/ip/firewall/address-list?list=bank-ips" 2>/dev/null | python3 -c "
import json,sys
try:
    for i in json.load(sys.stdin):
        if i.get('comment')=='bank-auto': print(i['.id'])
except: pass" | while read id; do curl -s -u "$AUTH" -X DELETE "$BASE/ip/firewall/address-list/$id" > /dev/null 2>&1; done
COUNT=0
for d in $DOMAINS; do
  IP=$(dig +short "$d" 2>/dev/null | grep -E '^[0-9]' | head -1)
  if [ -n "$IP" ]; then
    curl -s -u "$AUTH" -X POST "$BASE/ip/firewall/address-list" -H "Content-Type: application/json" -d "{\"list\":\"bank-ips\",\"address\":\"$IP\",\"comment\":\"bank-auto\"}" > /dev/null 2>&1
    curl -s -u "$AUTH" -X POST "$BASE/ip/hotspot/walled-garden/ip" -H "Content-Type: application/json" -d "{\"action\":\"accept\",\"dst-address\":\"$IP\",\"comment\":\"bank-auto\"}" > /dev/null 2>&1
    COUNT=$((COUNT+1))
  fi
  sleep 0.3
done
echo "$(date): $COUNT IPs em bank-ips"
