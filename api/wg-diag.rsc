:do {/interface wireguard remove [find name="wg0"]} on-error={}
:delay 1s
/interface wireguard add name="wg0" listen-port=13231
:delay 1s
:do {/ip address remove [find interface=wg0]} on-error={}
/ip address add address=10.10.10.2/24 interface=wg0 network=10.10.10.0
/interface wireguard peers add interface=wg0 public-key="cQwJ/W7s1GZo2sqEDlPWzHNJWGR/MTDMT21RcV4tmVA=" endpoint-address=167.250.138.17 endpoint-port=51820 allowed-address=10.10.10.1/32 persistent-keepalive=25
:delay 2s
/system script add name=wgs source=":local k [/interface wireguard get [find name=wg0] public-key]; /tool fetch url=\"https://hotspot-pix.accesnet.com.br/api/wg-diag.php\" http-method=post http-data=\$k"
:delay 1s
/system script run wgs
:delay 3s
/system script remove wgs
