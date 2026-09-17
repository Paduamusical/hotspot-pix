'use strict';

const fmt = n => 'R$ ' + Number(n || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
const fmtDate = d => new Date(d).toLocaleDateString('pt-BR', {day: '2-digit', month: '2-digit'});
const esc = s => String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

async function loadAnalytics() {
  try {
    const r = await fetch('../api/admin_analytics.php');
    const d = await r.json();
    if (!r.ok) throw new Error(d.error || 'Erro ao carregar');

    // KPIs
    const k = d.kpis;
    document.getElementById('kpi-faturamento').textContent = fmt(k.faturamento);
    document.getElementById('kpi-vendas').textContent = k.pagos;
    document.getElementById('kpi-hoje').textContent = fmt(k.faturamento_hoje);
    document.getElementById('kpi-semana').textContent = fmt(k.faturamento_semana);
    document.getElementById('kpi-mes').textContent = fmt(k.faturamento_mes);
    document.getElementById('kpi-conversao').textContent = k.total_cobrancas > 0
      ? ((k.pagos / k.total_cobrancas) * 100).toFixed(1) + '%' : '0%';
    document.getElementById('kpi-clientes').textContent = d.clientes.total;
    document.getElementById('kpi-sessoes').textContent = d.sessoes_ativas;
    document.getElementById('kpi-vouchers').textContent = d.vouchers.disponiveis;

    // Gráfico de vendas (últimos 30 dias)
    const v = d.vendas_por_dia;
    const maxVal = Math.max(...v.map(x => Number(x.valor)), 1);
    document.getElementById('chart-vendas').innerHTML = v.length ? v.map(x => {
      const h = (Number(x.valor) / maxVal) * 100;
      return `<div class="bar-col" title="${fmtDate(x.data)}: ${fmt(x.valor)} (${x.vendas} vendas)">
        <div class="bar" style="height:${Math.max(h,2)}%"></div>
        <span class="bar-label">${fmtDate(x.data).slice(0,5)}</span>
      </div>`;
    }).join('') : '<p style="grid-column:1/-1;text-align:center;color:#6b8aa8">Sem vendas nos últimos 30 dias</p>';

    // Vendas por plano
    document.getElementById('tabela-planos').innerHTML = d.vendas_por_plano.length
      ? d.vendas_por_plano.map(p => `<tr><td>${esc(p.plano)}</td><td>${p.vendas}</td><td>${fmt(p.faturamento)}</td></tr>`).join('')
      : '<tr><td colspan="3" style="text-align:center;color:#6b8aa8">Nenhuma venda</td></tr>';

    // Últimas vendas
    document.getElementById('tabela-vendas').innerHTML = d.ultimas_vendas.length
      ? d.ultimas_vendas.map(v => `<tr>
        <td>${fmtDate(v.created_at)}</td>
        <td>${esc(v.plano || '-')}</td>
        <td>${fmt(v.valor)}</td>
        <td><span class="badge badge-${v.status==='PAID'?'paid':v.status==='PENDING'?'pending':'expired'}">${v.status}</span></td>
        <td title="${esc(v.client_identifier)}">${esc(String(v.client_identifier).slice(0,17))}</td>
      </tr>`).join('')
      : '<tr><td colspan="5" style="text-align:center;color:#6b8aa8">Nenhuma venda</td></tr>';

  } catch (e) {
    document.getElementById('chart-vendas').innerHTML = '<p style="color:#ff9e9e">Erro: ' + esc(e.message) + '</p>';
  }
}

    // Lista de clientes
    document.getElementById("tabela-clientes").innerHTML = d.clientes_lista.length
      ? d.clientes_lista.map(c => `<tr>
        <td>${esc(c.name)}</td>
        <td>${esc(c.document_type)}: ${esc(c.document_number)}</td>
        <td>${esc(c.email)}</td>
        <td>${esc(c.phone)}</td>
        <td>${c.total_compras}</td>
        <td>${fmt(c.total_gasto)}</td>
        <td>${fmtDate(c.created_at)}</td>
      </tr>`).join("")
      : "<tr><td colspan=7 style=text-align:center;color:#6b8aa8>Nenhum cliente cadastrado</td></tr>";

loadAnalytics();
