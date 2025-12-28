// ❌ INCORRECTO (Ruta antigua v3)
// import preset from '../../../../vendor/filament/filament/tailwind.config.preset'

// ✅ CORRECTO (Ruta nueva v4)
import preset from '../../../../vendor/filament/support/tailwind.config.preset'

export default {
    presets: [preset],
    content: [
        '../../../../app/Filament/**/*.php',
        '../../../../resources/views/filament/**/*.blade.php',
        '../../../../vendor/filament/**/*.blade.php',
    ],
}