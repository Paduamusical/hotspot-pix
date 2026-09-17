# Hotspot PIX

Portal captive para venda de acesso Wi-Fi, painel exclusivo do administrador e integração preparada para PIX Cob do Banco Inter e RouterOS v7 REST.

## Instalação

1. Crie um banco MySQL e importe `sql/schema.sql`.
2. Copie `.env.example` para `.env` e preencha todas as variáveis. Mantenha `.env` fora do diretório público em produção.
3. Sirva esta pasta com PHP 8.1+ e extensões `pdo_mysql`, `curl` e `openssl. Exemplo: `php -S localhost:8080`.
4. Acesse `http://localhost:8080/index.html` e `http://localhost:8080/admin/login.php`.
5. No MikroTik RouterOS v7, ative `www-ssl`, crie o usuário `hotspot_api` com o mínimo de permissões e permita HTTPS à API somente a partir do servidor PHP.

## Fluxo PIX

O navegador pede uma cobrança; o servidor cria uma Cob imediata no Inter e devolve o payload copia-e-cola e QR. O front consulta o estado a cada 5 segundos. Ao confirmar, o servidor cria/atualiza o usuário do Hotspot no RouterOS. Para produção, configure também o webhook PIX do Inter apontando a `api/webhook.php` e valide sua origem/certificado conforme a documentação contratada.

> A ativação de acesso é feita somente no servidor após confirmação consultada no Inter; nunca confie no retorno do navegador.

## MikroTik

Este exemplo cria um usuário `/ip/hotspot/user` com `name` igual ao MAC ou identificador recebido e `limit-uptime` definido pelo plano. Ajuste `RouterService::grantHotspotAccess()` se seu captive portal usa RADIUS, vouchers ou perfis específicos. O portal pode receber o MAC da página de login do MikroTik em `?client=AA:BB:CC:DD:EE:FF`.

## Segurança antes de publicar

- Use HTTPS obrigatório e certificados válidos.
- Guarde `.env`, certificados e chave privada fora do webroot e em cofre de segredos.
- Altere a senha inicial do admin; a aplicação só a semeia quando não há administradores.
- Restrinja a REST API do RouterOS por firewall/IP e use usuário sem permissões administrativas globais.
- Configure callback/webhook oficial do Inter e registre auditoria de concessões.
