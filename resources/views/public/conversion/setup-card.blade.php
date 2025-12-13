<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Método de pago — Tarjeta</title>

  <link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}">
  <script src="https://js.stripe.com/v3/"></script>
  
  {{-- Fuente Inter para consistencia --}}
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

    /* Logo centrado */
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
    
    /* Icono Circular Azul */
    .icon-card {
        width: 60px; height: 60px;
        background: rgba(59, 130, 246, 0.1);
        color: var(--primary);
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        margin: 0 auto 20px auto;
    }

    h1 { 
        font-size: 22px; font-weight: 700; margin: 0 0 10px 0; color: #fff; 
    }
    p.desc { 
        color: var(--text-muted); font-size: 15px; margin: 0; 
    }

    /* Estilo del Formulario */
    label {
        display: block; font-size: 13px; font-weight: 600; 
        color: #cbd5e1; margin-bottom: 8px; margin-left: 2px;
    }

    #card-element {
        padding: 14px 16px;
        border-radius: 12px;
        border: 1px solid #334155;
        background: var(--input-bg);
        transition: border-color 0.2s, box-shadow 0.2s;
        margin-bottom: 6px;
    }
    
    /* Efecto foco azul */
    #card-element.StripeElement--focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 2px rgba(56, 189, 248, 0.2);
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
        margin-top: 24px; color: #64748b; font-size: 12px;
    }
    
    /* Logos de tarjetas aceptadas */
    .card-logos {
        display: flex; justify-content: center; gap: 12px; margin-top: 20px; opacity: 0.6;
    }
    .card-logos svg { height: 20px; width: auto; filter: grayscale(100%); transition: filter 0.3s; }
    .card-logos:hover svg { filter: grayscale(0%); }

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
          {{-- Icono Tarjeta Azul --}}
          <div class="icon-card">
            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg>
          </div>
          <h1>Configurar Tarjeta</h1>
          <p class="desc">Añade una tarjeta segura para automatizar tu cuota mensual.</p>
      </div>

      {{-- Mensaje Error PHP --}}
      @if(session('error'))
        <div class="err" style="display:block;">{{ session('error') }}</div>
      @endif

      {{-- Mensaje Error JS --}}
      <div id="card-errors" class="err"></div>

      <form id="payment-form" method="POST" action="{{ route('stripe.process-card', ['token' => $token]) }}">
        @csrf

        <label>Datos de tarjeta</label>
        <div id="card-element">
            </div>
        
        <div style="text-align: right; font-size: 11px; color: #64748b; margin-top: 4px;">
            🔒 Datos encriptados y procesados directamente por Stripe.
        </div>

        <input type="hidden" id="payment_method" name="payment_method">

        <button class="btn" id="save-btn">
            Guardar Tarjeta Segura
        </button>
        
        <div class="card-logos">
            <svg viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg"><path fill="#fff" d="M12.75 30h-3.5l2.25-13.75h3.5l-2.25 13.75zm5.25-13.75l-2.25 13.75h-3.5l2.25-13.75h3.5zm7.25 0l-1.5 7.5c-.25 1.25-.75 1.75-2 1.75h-3l2-9.25h4.5zM31.5 30h-4.25c-.75 0-1.5-.25-1.75-1l-5.75-12.75h4.5l2.5 6.75 2.25-6.75h2.5z"/></svg>
            <svg viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg"><circle fill="#EB001B" cx="11" cy="16" r="10"/><circle fill="#F79E1B" cx="21" cy="16" r="10"/><path fill="#FF5F00" d="M16 9.8c-1.8 1.8-2.8 4.2-2.8 6.8s1 5 2.8 6.8c1.8-1.8 2.8-4.2 2.8-6.8s-1-5-2.8-6.8z"/></svg>
        </div>

        <div class="secure-badge">
            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            <span>Certificado SSL 256-bit</span>
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

// Estilo personalizado para el input interno de Stripe
const style = {
    base: {
        color: "#f1f5f9",
        fontFamily: '"Inter", sans-serif',
        fontSmoothing: "antialiased",
        fontSize: "16px",
        "::placeholder": {
            color: "#64748b"
        },
        iconColor: "#38bdf8" // Color icono azul
    },
    invalid: {
        color: "#fca5a5",
        iconColor: "#ef4444"
    }
};

const card = elements.create("card", { 
    style: style,
    hidePostalCode: true // Opcional: Oculta CP para simplificar, quítalo si lo necesitas
});

card.mount("#card-element");

// Manejo de foco para el borde
const container = document.getElementById('card-element');
card.on('focus', () => container.classList.add('StripeElement--focus'));
card.on('blur', () => container.classList.remove('StripeElement--focus'));
card.on('change', (event) => {
    const displayError = document.getElementById('card-errors');
    if (event.error) {
        displayError.textContent = event.error.message;
        displayError.style.display = 'block';
    } else {
        displayError.textContent = '';
        displayError.style.display = 'none';
    }
});

const form = document.getElementById("payment-form");
const saveBtn = document.getElementById("save-btn");

form.addEventListener("submit", async (e) => {
    e.preventDefault();
    
    saveBtn.disabled = true;
    const originalText = saveBtn.textContent;
    saveBtn.textContent = "Procesando...";

    const { setupIntent, error } = await stripe.confirmCardSetup(
        "{{ $clientSecret }}",
        {
            payment_method: {
                card: card,
                billing_details: {
                    name: "{{ $cliente->razon_social }}",
                    email: "{{ $cliente->email_contacto }}"
                }
            }
        }
    );

    if (error) {
        const displayError = document.getElementById("card-errors");
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