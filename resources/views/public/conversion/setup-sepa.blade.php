<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Domiciliación bancaria — SEPA</title>

  <link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}">
  <script src="https://js.stripe.com/v3/"></script>
  
  {{-- Fuente Inter --}}
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

  <style>
    :root {
      --bg:#0b1220;
      --card:#0f172a;
      --border:#1e293b;
      --text-main:#f1f5f9;
      --text-muted:#94a3b8;
      
      /* 🔵 PALETA AZUL ASESORFY */
      --primary: #38bdf8;        /* Azul claro para iconos/bordes */
      --btn-grad-start: #3b82f6; /* Azul intenso */
      --btn-grad-end: #2563eb;   /* Azul oscuro */
      
      --error:#ef4444;
      --input-bg: #1e293b;
    }

    body {
      background: var(--bg);
      color: var(--text-main);
      font-family: 'Inter', ui-sans-serif, system-ui;
      margin: 0;
      padding: 40px 20px;
      line-height: 1.5;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: center;
    }

    .wrap { width: 100%; max-width: 520px; margin: 0 auto; }

    .logo-container {
        text-align: center; margin-bottom: 30px;
    }
    .logo-img { height: 40px; width: auto; }

    .card {
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: 20px;
      padding: 40px;
      box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
      position: relative;
      overflow: hidden;
    }
    
    /* Brillo de fondo sutil (Azul) */
    .card::before {
        content: ''; position: absolute; top: -50px; right: -50px; width: 150px; height: 150px;
        background: radial-gradient(circle, rgba(56, 189, 248, 0.15) 0%, rgba(0,0,0,0) 70%);
        pointer-events: none;
    }

    .header { text-align: center; margin-bottom: 30px; position: relative; z-index: 2; }
    
    /* Icono Banco Azul */
    .icon-bank {
        width: 60px; height: 60px;
        background: rgba(59, 130, 246, 0.1);
        color: var(--primary);
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        margin: 0 auto 20px auto;
        font-size: 28px;
    }

    h1 { 
        font-size: 22px; font-weight: 700; margin: 0 0 10px 0; color: #fff; 
    }
    p.desc { 
        color: var(--text-muted); font-size: 15px; margin: 0; 
    }

    label {
        display: block; font-size: 13px; font-weight: 600; 
        color: #cbd5e1; margin-bottom: 8px; margin-left: 2px;
    }

    #iban-element {
        padding: 14px 16px;
        border-radius: 12px;
        border: 1px solid #334155;
        background: var(--input-bg);
        transition: border-color 0.2s, box-shadow 0.2s;
    }
    
    /* Efecto foco Azul */
    #iban-element.StripeElement--focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 2px rgba(56, 189, 248, 0.2);
    }

    /* Caja del Mandato (Estilo neutro/azulado) */
    .mandato-box {
        margin-top: 24px;
        background: rgba(15, 23, 42, 0.6);
        border: 1px solid #334155;
        border-radius: 10px;
        padding: 16px;
        font-size: 12px;
        color: #94a3b8;
        line-height: 1.5;
    }
    .mandato-title {
        font-weight: 700; color: #cbd5e1; margin-bottom: 4px; display: block;
        text-transform: uppercase; letter-spacing: 0.5px; font-size: 11px;
    }

    .btn {
        display: block;
        width: 100%;
        border-radius: 12px;
        padding: 14px;
        background: linear-gradient(135deg, var(--btn-grad-start), var(--btn-grad-end));
        color: #fff;
        text-align: center;
        font-weight: 600;
        font-size: 16px;
        border: none;
        cursor: pointer;
        margin-top: 24px;
        transition: transform 0.1s, box-shadow 0.2s;
        box-shadow: 0 4px 6px rgba(59, 130, 246, 0.25);
    }
    .btn:hover { 
        transform: translateY(-1px);
        box-shadow: 0 6px 12px rgba(59, 130, 246, 0.35);
    }
    .btn:disabled { opacity: 0.7; cursor: not-allowed; transform: none; }

    .err { 
        background: rgba(239, 68, 68, 0.1); 
        border: 1px solid rgba(239, 68, 68, 0.3);
        color: #fca5a5; 
        font-size: 13px; 
        padding: 10px; 
        border-radius: 8px;
        margin-top: 16px; 
        display: none; 
    }
    
    .secure-badge {
        display: flex; align-items: center; justify-content: center; gap: 6px;
        margin-top: 20px; color: #64748b; font-size: 12px;
    }

    @media (max-width: 480px) {
        .card { padding: 24px; border: none; background: transparent; box-shadow: none; }
        .wrap { padding: 0; }
        body { padding: 20px 0; }
    }
  </style>
</head>

<body>

<div class="wrap">
  
  <div class="logo-container">
     <img src="{{ asset('images/logo.png') }}" alt="AsesorFy" class="logo-img" 
          onerror="this.style.display='none'">
  </div>

  <div class="card">

      <div class="header">
          <div class="icon-bank">
            <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M5 21V7"/><path d="M19 21V7"/><path d="M10 21V11"/><path d="M14 21V11"/><rect x="3" y="3" width="18" height="4" rx="1"/></svg>
          </div>
          <h1>Domiciliación Bancaria</h1>
          <p class="desc">Configura tu IBAN para automatizar la cuota mensual.</p>
      </div>

      @if(session('error'))
        <div class="err" style="display:block;">{{ session('error') }}</div>
      @endif

      <div id="iban-errors" class="err"></div>

      <form id="sepa-form" method="POST" action="{{ route('stripe.process-sepa', ['token' => $token]) }}">
        @csrf

        <label>IBAN (Cuenta bancaria)</label>
        <div id="iban-element">
            </div>

        <input type="hidden" id="payment_method" name="payment_method">

        <div class="mandato-box">
            <span class="mandato-title">⚖️ Mandato SEPA</span>
            Al proporcionar tu IBAN y confirmar, estás autorizando a <strong>AsesorFy</strong> a enviar instrucciones a tu banco para adeudar tu cuenta y a tu banco para efectuar dichos adeudos siguiendo las instrucciones de AsesorFy. Tienes derecho a reembolso de acuerdo con los términos y condiciones de tu contrato con tu banco.
        </div>

        <button class="btn" id="save-btn">
            Confirmar y Guardar IBAN
        </button>
        
        <div class="secure-badge">
            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
            Encriptación segura SSL vía Stripe
        </div>

      </form>

  </div>
</div>

<script>
const stripe = Stripe("{{ config('services.stripe.key') }}");
const elements = stripe.elements({
    fonts: [
        { cssSrc: 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap' }
    ]
});

// Estilo personalizado AZUL para el input de Stripe
const style = {
    base: {
        color: "#f1f5f9",
        fontFamily: '"Inter", sans-serif',
        fontSmoothing: "antialiased",
        fontSize: "16px",
        "::placeholder": {
            color: "#64748b"
        },
        iconColor: "#38bdf8" // Icono del banco AZUL CLARO
    },
    invalid: {
        color: "#fca5a5",
        iconColor: "#ef4444"
    }
};

const iban = elements.create("iban", {
    supportedCountries: ['SEPA'],
    style: style,
    placeholderCountry: 'ES'
});

iban.mount("#iban-element");

// Manejo de Foco
const container = document.getElementById('iban-element');
iban.on('focus', () => container.classList.add('StripeElement--focus'));
iban.on('blur', () => container.classList.remove('StripeElement--focus'));
iban.on('change', (event) => {
    const displayError = document.getElementById('iban-errors');
    if (event.error) {
        displayError.textContent = event.error.message;
        displayError.style.display = 'block';
    } else {
        displayError.textContent = '';
        displayError.style.display = 'none';
    }
});

const form = document.getElementById("sepa-form");
const saveBtn = document.getElementById("save-btn");

form.addEventListener("submit", async (e) => {
    e.preventDefault();
    
    saveBtn.disabled = true;
    const originalText = saveBtn.textContent;
    saveBtn.textContent = "Procesando...";

    const { setupIntent, error } = await stripe.confirmSepaDebitSetup(
        "{{ $clientSecret }}",
        {
            payment_method: {
                sepa_debit: iban,
                billing_details: {
                    name: "{{ $cliente->razon_social }}",
                    email: "{{ $cliente->email_contacto }}"
                }
            }
        }
    );

    if (error) {
        const displayError = document.getElementById("iban-errors");
        displayError.textContent = error.message;
        displayError.style.display = 'block';
        
        saveBtn.disabled = false;
        saveBtn.textContent = originalText;
        return;
    }

    document.getElementById("payment_method").value = setupIntent.payment_method;
    form.submit();
});
</script>

</body>
</html>