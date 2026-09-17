(function(){
'use strict';

var params = new URLSearchParams(location.search);
var client = params.get('client') || '';
var customerId = null;

if (!client) {
  document.getElementById('app').innerHTML =
    '<div class="success-screen"><div class="success-icon">⚠️</div>' +
    '<h2>Erro</h2><p>Identificador do dispositivo não encontrado.</p></div>';
  return;
}

var currentTxid = null;
var pollTimer = null;
var app = document.getElementById('app');

// ===== WEB PUSH =====
var vapidKey = null;
if ('serviceWorker' in navigator) {
  navigator.serviceWorker.register('/sw.js').catch(function(){});
}

function subscribePush() {
  if (!('serviceWorker' in navigator) || !('PushManager' in window)) return;
  navigator.serviceWorker.ready.then(function(reg) {
    reg.pushManager.getSubscription().then(function(sub) {
      if (sub) { sendSubscription(sub); return; }
      fetch('api/subscribe.php').then(function(r){return r.json();}).then(function(d){
        if (!d.vapidPublicKey) return;
        vapidKey = d.vapidPublicKey;
        reg.pushManager.subscribe({
          userVisibleOnly: true,
          applicationServerKey: urlB64ToUint8Array(vapidKey)
        }).then(function(sub) {
          sendSubscription(sub);
        }).catch(function(){});
      }).catch(function(){});
    });
  });
}

function sendSubscription(sub) {
  var subData = {
    client: client,
    endpoint: sub.endpoint,
    keys: { p256dh: sub.keys ? sub.keys.p256dh : '', auth: sub.keys ? sub.keys.auth : '' }
  };
  fetch('api/subscribe.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify(subData)
  }).catch(function(){});
}

function urlB64ToUint8Array(base64String) {
  var padding = '='.repeat((4 - base64String.length % 4) % 4);
  var base64 = (base64String + padding).replace(/\-/g, '+').replace(/_/g, '/');
  var raw = atob(base64);
  var arr = new Uint8Array(raw.length);
  for (var i = 0; i < raw.length; i++) arr[i] = raw.charCodeAt(i);
  return arr;
}

function askNotificationPermission() {
  if (!('Notification' in window)) return;
  if (Notification.permission === 'granted') { subscribePush(); return; }
  if (Notification.permission === 'default') {
    Notification.requestPermission().then(function(p) {
      if (p === 'granted') subscribePush();
    });
  }
}

showRegistration();

function showRegistration(){
  app.innerHTML =
    '<img src="assets/img/logo-accesnet.jpg" alt="Accesnet" class="login-logo">' + '<h2>Cadastro</h2>' +
    '<p class="note">Para acessar a internet, precisamos de alguns dados conforme a LGPD (Lei nº 13.709/2018).</p>' +
    '<div class="register-form">' +
      '<label class="form-label">Nome completo</label>' +
      '<input type="text" class="form-input" id="reg-name" placeholder="Seu nome completo" autocomplete="name">' +
      '<label class="form-label">Tipo</label>' +
      '<select class="form-input" id="reg-doc-type">' +
        '<option value="CPF">CPF</option>' +
        '<option value="CNPJ">CNPJ</option>' +
      '</select>' +
      '<label class="form-label" id="reg-doc-label">CPF</label>' +
      '<input type="text" class="form-input" id="reg-doc" placeholder="000.000.000-00" autocomplete="off">' +
      '<label class="form-label">E-mail</label>' +
      '<input type="email" class="form-input" id="reg-email" placeholder="seu@email.com" autocomplete="email">' +
      '<label class="form-label">Telefone / WhatsApp</label>' +
      '<input type="tel" class="form-input" id="reg-phone" placeholder="(11) 99999-9999" autocomplete="tel">' +
      '<label class="consent-row">' +
        '<input type="checkbox" id="reg-consent">' +
        '<span>Concordo com o tratamento dos meus dados para cobrança e acesso à internet, conforme a <a href="privacy.html" target="_blank">Política de Privacidade</a>.</span>' +
      '</label>' +
      '<label class="consent-row">' +
        '<input type="checkbox" id="reg-marketing">' +
        '<span>Desejo receber promoções e ofertas por e-mail e WhatsApp.</span>' +
      '</label>' +
      '<button class="btn-release" id="reg-btn" style="margin-top:16px">Continuar</button>' +
      '<div class="status" id="reg-status"></div>' +
      '<div class="divider">ou</div>' +
      '<p class="note" style="text-align:center;margin-bottom:10px">Já é cadastrado?</p>' +
      '<button class="btn-secondary" id="reg-login-btn">Entrar com CPF/CNPJ</button>' +
    '</div>';

  document.getElementById('reg-doc-type').onchange = function(){
    var t = this.value;
    document.getElementById('reg-doc-label').textContent = t;
    document.getElementById('reg-doc').placeholder = t === 'CPF' ? '000.000.000-00' : '00.000.000/0000-00';
  };

  document.getElementById('reg-doc').oninput = function(){
    var v = this.value.replace(/\D/g, '');
    if (document.getElementById('reg-doc-type').value === 'CPF') {
      v = v.replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d{1,2})$/, '$1-$2');
      if (v.length > 14) v = v.substring(0, 14);
    } else {
      v = v.replace(/(\d{2})(\d)/, '$1.$2').replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d)/, '$1/$2').replace(/(\d{4})(\d{1,2})$/, '$1-$2');
      if (v.length > 18) v = v.substring(0, 18);
    }
    this.value = v;
  };

  document.getElementById('reg-phone').oninput = function(){
    var v = this.value.replace(/\D/g, '');
    if (v.length <= 10) {
      v = v.replace(/(\d{2})(\d)/, '($1) $2').replace(/(\d{4})(\d)$/, '$1-$2');
    } else {
      v = v.replace(/(\d{2})(\d)/, '($1) $2').replace(/(\d{5})(\d)$/, '$1-$2');
    }
    this.value = v;
  };

  document.getElementById('reg-btn').onclick = registerCustomer;
  document.getElementById('reg-login-btn').onclick = showLoginLookup;
}

function showLoginLookup(){
  app.innerHTML =
    '<h2>Entrar</h2>' +
    '<p class="note">Informe seu CPF ou CNPJ para continuar.</p>' +
    '<div class="register-form">' +
      '<label class="form-label">Tipo</label>' +
      '<select class="form-input" id="login-doc-type">' +
        '<option value="CPF">CPF</option>' +
        '<option value="CNPJ">CNPJ</option>' +
      '</select>' +
      '<label class="form-label">CPF / CNPJ</label>' +
      '<input type="text" class="form-input" id="login-doc" placeholder="000.000.000-00">' +
      '<button class="btn-release" id="login-lookup-btn" style="margin-top:16px">Entrar</button>' +
      '<div class="status" id="login-status"></div>' +
      '<button class="btn-secondary" id="login-back-btn" style="margin-top:12px">← Voltar ao cadastro</button>' +
    '</div>';

  document.getElementById('login-back-btn').onclick = showRegistration;
  document.getElementById('login-lookup-btn').onclick = lookupCustomer;
}

function lookupCustomer(){
  var docType = document.getElementById('login-doc-type').value;
  var docNumber = document.getElementById('login-doc').value.replace(/\D/g, '');
  var status = document.getElementById('login-status');

  if (!docNumber) { status.innerHTML = '<p class="status status--error">Informe seu CPF/CNPJ.</p>'; return; }

  var btn = document.getElementById('login-lookup-btn');
  btn.disabled = true;
  btn.textContent = 'Buscando...';

  fetch('api/customer.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({action: 'lookup', document_type: docType, document_number: docNumber})
  })
  .then(function(r){return r.json()})
  .then(function(data){
    btn.disabled = false;
    btn.textContent = 'Entrar';
    if (data.error) { status.innerHTML = '<p class="status status--error">' + data.error + '</p>'; return; }
    customerId = data.customer.id;
    showPlans();
  })
  .catch(function(){
    btn.disabled = false;
    btn.textContent = 'Entrar';
    status.innerHTML = '<p class="status status--error">Erro ao buscar cadastro.</p>';
  });
}

function registerCustomer(){
  var name = document.getElementById('reg-name').value.trim();
  var docType = document.getElementById('reg-doc-type').value;
  var docNumber = document.getElementById('reg-doc').value.replace(/\D/g, '');
  var email = document.getElementById('reg-email').value.trim();
  var phone = document.getElementById('reg-phone').value.trim();
  var consent = document.getElementById('reg-consent').checked;
  var marketing = document.getElementById('reg-marketing').checked;
  var status = document.getElementById('reg-status');

  var btn = document.getElementById('reg-btn');
  btn.disabled = true;
  btn.textContent = 'Cadastrando...';
  status.innerHTML = '';

  fetch('api/customer.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({
      action: 'register',
      name: name,
      document_type: docType,
      document_number: docNumber,
      email: email,
      phone: phone,
      consent: consent,
      marketing_consent: marketing,
      client: client
    })
  })
  .then(function(r){return r.json()})
  .then(function(data){
    btn.disabled = false;
    btn.textContent = 'Continuar';
    if (data.error) { status.innerHTML = '<p class="status status--error">' + data.error + '</p>'; return; }
    customerId = data.customer_id;
    showPlans();
  })
  .catch(function(){
    btn.disabled = false;
    btn.textContent = 'Continuar';
    status.innerHTML = '<p class="status status--error">Erro ao cadastrar. Tente novamente.</p>';
  });
}

function showPlans(){
  app.innerHTML =
    '<div class="plans-title">Escolha seu plano</div>' +
    '<div class="plans-list" id="plans-list"><div class="loading"><span class="spinner"></span>Carregando planos...</div></div>' +
    '<div class="payment-section" id="payment-section" style="display:none">' +
      '<div class="plans-title">Pagamento PIX</div>' +
      '<div id="qr-area"></div>' +
      '<div id="status-area"></div>' +
    '</div>' +
    '<div class="divider">ou</div>' +
    '<div class="voucher-section">' +
      '<div class="plans-title">Tem um voucher?</div>' +
      '<input type="text" class="voucher-input" id="voucher-code" placeholder="Digite o código do voucher">' +
      '<button class="btn-secondary" id="voucher-btn">Usar Voucher</button>' +
    '</div>';

  document.getElementById('voucher-btn').onclick = useVoucher;

  fetch('api/plans.php')
    .then(function(r){return r.json()})
    .then(function(data){
      var plans = data.plans || data || [];
      var list = document.getElementById('plans-list');
      if (!plans.length) { list.innerHTML = '<p class="status">Nenhum plano disponível.</p>'; return; }
      list.innerHTML = '';
      plans.forEach(function(plan){
        var btn = document.createElement('button');
        btn.className = 'plan-card';
        var price = (plan.price_cents / 100).toFixed(2).replace('.', ',');
        var badge = plan.duration_minutes >= 1440 ? '<span class="plan-badge">Popular</span>' : '';
        btn.innerHTML =
          '<span><span class="plan-name">' + plan.name + '</span>' + badge + '</span>' +
          '<span class="plan-price">R$ ' + price + '</span>';
        btn.onclick = function(){ createPayment(plan.id); };
        list.appendChild(btn);
      });
    })
    .catch(function(){ document.getElementById('plans-list').innerHTML = '<p class="status status--error">Erro ao carregar planos.</p>'; });
}

function createPayment(planId){
  var paySec = document.getElementById('payment-section');
  paySec.style.display = 'block';
  document.getElementById('qr-area').innerHTML = '<div class="loading"><span class="spinner"></span>Gerando QR Code PIX...</div>';
  document.getElementById('status-area').innerHTML = '';
  paySec.scrollIntoView({behavior:'smooth', block:'nearest'});

  fetch('api/pix.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({action: 'create', plan_id: planId, client: client, customer_id: customerId})
  })
  .then(function(r){return r.json()})
  .then(function(data){
    if (data.error) {
      document.getElementById('qr-area').innerHTML = '<p class="status status--error">' + data.error + '</p>';
      return;
    }
    if (data.status === 'ALREADY_PAID' || data.granted) {
      showSuccess(data);
      return;
    }
    currentTxid = data.txid || data.qr_text || '';

    var qrImage = data.image || data.qr_code || null;
    var qrCodeText = data.copy_paste || data.qr_text || '';

    var html = '<div class="qr-wrap">';
    if (qrImage) {
      var imgSrc = qrImage.indexOf('data:') === 0 ? qrImage : 'data:image/png;base64,' + qrImage;
      html += '<img class="qr-img" src="' + imgSrc + '" alt="QR Code PIX">';
    }
    if (qrCodeText) {
      html += '<div class="qr-label">Código PIX (Copia e Cola):</div>';
      html += '<textarea class="qr-code" readonly id="qr-copy" rows="3">' + qrCodeText + '</textarea>';
      html += '<button class="btn-secondary" id="copy-btn" style="margin-top:10px">Copiar código PIX</button>';
    }
    html += '</div>';
    document.getElementById('qr-area').innerHTML = html;

    var copyBtn = document.getElementById('copy-btn');
    if (copyBtn) copyBtn.onclick = function(){
      var t = document.getElementById('qr-copy');
      t.select(); document.execCommand('copy');
      copyBtn.textContent = '✓ Copiado!';
      setTimeout(function(){ copyBtn.textContent = 'Copiar código PIX'; }, 2000);
    };

    showConnectBtn(data);
    startPolling();
  })
  .catch(function(){ document.getElementById('qr-area').innerHTML = '<p class="status status--error">Erro ao gerar pagamento.</p>'; });
}

function showConnectBtn(j){
  var old = document.querySelector('#pay-section');
  if (old) old.remove();
  var sec = document.createElement('div');
  sec.id = 'pay-section';
  sec.className = 'pay-section';
  sec.innerHTML = '<button class="btn-warning" id="connect-pay-btn">Conectar para pagar no banco</button><p class="pay-hint">Acesso de 5 minutos apenas para apps de banco</p>';
  document.getElementById('qr-area').appendChild(sec);

  document.getElementById('connect-pay-btn').onclick = function(){
    var btn = this;
    btn.disabled = true;
    btn.textContent = 'Conectando...';
    askNotificationPermission();

    fetch('api/pix.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({action: 'connect', client: client})
    })
    .then(function(r){return r.json()})
    .then(function(c){
      if (c.status === 'ALREADY_PAID' || c.granted) {
        showSuccess(c);
        return;
      }
      if (!c.status || c.status !== 'CONNECTED') throw new Error(c.error || 'Falha ao liberar acesso');

      var tempCmdId = c.cmd_id || j.cmd_id || null;

      sec.innerHTML =
        '<p class="status status--success">⏳ Aguardando liberação de 5 minutos...</p>' +
        '<div class="loading"><span class="spinner"></span></div>' +
        '<p class="pay-hint">Você poderá acessar o banco em alguns segundos.</p>';

      function showTempReady() {
        sec.innerHTML =
          '<p class="status status--success">✓ Acesso de 5 minutos liberado!</p>' +
          '<p class="pay-hint">Abra o app do seu banco e pague o PIX.</p>' +
          '<p class="pay-hint">Não quer pagar este plano? Escolha outro:</p>' +
          '<button class="btn-secondary" id="back-plans-btn" style="margin-top:10px">← Voltar aos planos</button>';
        document.getElementById('back-plans-btn').onclick = function(){
          var paySec = document.getElementById('payment-section');
          if (paySec) paySec.style.display = 'none';
          showPlans();
        };
      }

      if (tempCmdId) {
        var tempPollTimer = setInterval(function(){
          fetch('api/pix.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'temp_status', cmd_id: tempCmdId})
          })
          .then(function(r){return r.json()})
          .then(function(data){
            if (data.done) {
              clearInterval(tempPollTimer);
              showTempReady();
            }
          })
          .catch(function(){});
        }, 5000);
      } else {
        setTimeout(showTempReady, 10000);
      }
    })
    .catch(function(e){
      btn.disabled = false;
      btn.textContent = 'Conectar para pagar no banco';
      sec.innerHTML += '<p class="status status--error">Erro: ' + e.message + '</p>';
    });
  };
}

function startPolling(){
  if (pollTimer) clearInterval(pollTimer);
  pollTimer = setInterval(function(){
    if (!currentTxid) return;
    fetch('api/pix.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({action: 'status', txid: currentTxid, client: client})
    })
    .then(function(r){return r.json()})
    .then(function(data){
      if (data.status === 'PAID' || data.granted) {
        if (data.mikrotik_done) {
          clearInterval(pollTimer);
          showSuccess(data);
        } else {
          showWaitingMikrotik();
        }
      }
    })
    .catch(function(){});
  }, 5000);
}

function showWaitingMikrotik(){
  var existing = document.getElementById('waiting-mikrotik');
  if (existing) return;
  app.innerHTML =
    '<div class="success-screen" id="waiting-mikrotik">' +
      '<div class="success-icon">⏳</div>' +
      '<h2>Pagamento Confirmado!</h2>' +
      '<p>Aguardando liberação da internet...</p>' +
      '<div class="loading"><span class="spinner"></span></div>' +
      '<p class="success-hint">Isso leva alguns segundos. Não feche esta página.</p>' +
    '</div>';
}

function showSuccess(data){
  if (pollTimer) clearInterval(pollTimer);
  var loginUrl = '#';
  if (data.login_url) {
    loginUrl = data.login_url + '?username=' + encodeURIComponent(data.username || client) + '&password=' + encodeURIComponent(data.password || client) + '&dst=' + encodeURIComponent('http://connectivitycheck.gstatic.com/generate_204');
  }
  if (loginUrl !== '#') {
    app.innerHTML =
      '<div class="success-screen">' +
        '<div class="success-icon">✅</div>' +
        '<h2>Internet Liberada!</h2>' +
        '<p>' + (data.message || 'Seu pacote foi ativado com sucesso.') + '</p>' +
        '<a href="' + loginUrl + '" style="display:block;width:100%;max-width:300px;margin:15px auto 0;padding:15px;background:linear-gradient(135deg,#00b894,#00cec9);color:#fff;border:none;border-radius:10px;font-size:18px;font-weight:bold;text-decoration:none;text-align:center;cursor:pointer">Conectar e navegar</a>' +
        '<p style="font-size:12px;color:#999;margin-top:10px">Toque no botao acima para liberar a internet</p>' +
      '</div>';
    fetch(loginUrl, {mode: 'no-cors'}).catch(function(){});
  } else {
    app.innerHTML =
      '<div class="success-screen">' +
        '<div class="success-icon">✅</div>' +
        '<h2>Internet Liberada!</h2>' +
        '<p>Seu pacote foi ativado com sucesso. Redirecionando...</p>' +
        '<div class="loading"><span class="spinner"></span></div>' +
      '</div>';
    setTimeout(function() {
      window.location.href = 'http://neverssl.com';
    }, 2000);
  }
}

function useVoucher(){
  var code = document.getElementById('voucher-code').value.trim();
  if (!code) return;
  var btn = document.getElementById('voucher-btn');
  btn.textContent = 'Validando...';
  btn.disabled = true;

  fetch('api/pix.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({action: 'voucher', code: code, client: client, customer_id: customerId})
  })
  .then(function(r){return r.json()})
  .then(function(data){
    btn.textContent = 'Usar Voucher';
    btn.disabled = false;
    if (data.error) { alert(data.error); return; }
    if (data.cmd_id) {
      showWaitingMikrotik();
      var vPollTimer = setInterval(function(){
        fetch('api/pix.php', {
          method: 'POST',
          headers: {'Content-Type': 'application/json'},
          body: JSON.stringify({action: 'temp_status', cmd_id: data.cmd_id})
        })
        .then(function(r){return r.json()})
        .then(function(d){
          if (d.done) {
            clearInterval(vPollTimer);
            showSuccess(data);
          }
        })
        .catch(function(){});
      }, 5000);
    } else {
      showSuccess(data);
    }
  })
  .catch(function(){
    btn.textContent = 'Usar Voucher';
    btn.disabled = false;
    alert('Erro ao validar voucher.');
  });
}
})();
