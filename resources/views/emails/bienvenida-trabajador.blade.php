<!DOCTYPE html>
<html>
<head>
    <title>Bienvenido a AsesorFy</title>
    
    <style type="text/css"> 
        @import url('https://fonts.googleapis.com/css2?family=Varela+Round&display=swap');
    </style>

</head>
<body style="font-family: 'Varela Round', Arial, sans-serif; margin: 20px; color: #333;">

    <div style="text-align: center; margin-bottom: 30px;">
        <img src="{{ asset('images/logo.png') }}" alt="Logo de AsesorFy" style="max-width: 180px; height: auto;">
    </div>

    <div style="padding: 20px; border: 1px solid #eee; border-radius: 8px; text-align: center;">
        
        <h1 style="font-family: 'Varela Round', Arial, sans-serif; color: #222;">
            ¡Bienvenido a AsesorFy, {{ $user->name }}!
        </h1>
        
        <p>Te hemos dado de alta en la plataforma de trabajo de AsesorFy.</p>

        <p>Tu email de acceso es este mismo: <strong>{{ $user->email }}</strong></p>


        <p><strong>Tu administrador o coordinador te facilitará la contraseña</strong> para tu primer inicio de sesión.</p>

        <p>Puedes acceder a la plataforma desde este enlace:</p>
        
        <a href="{{ $accessUrl }}" style="
            display: inline-block;
            padding: 12px 20px;
            margin-top: 15px;
            background-color: #42c0e9; 
            color: #ffffff;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            font-family: 'Varela Round', Arial, sans-serif;
        ">
            Acceder a la Plataforma
        </a>
        
        <p style="margin-top: 25px; font-size: 0.9em; color: #777;">
            Si tienes problemas con el botón, copia y pega esta URL:<br>
            {{ $accessUrl }}
        </p>
    </div>

</body>
</html>