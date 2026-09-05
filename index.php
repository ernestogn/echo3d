<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Echo3D Laser</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background-color: #000000;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
        }
        
        .logo-container {
            width: 200px;
            height: 200px;
            display: flex;
            justify-content: center;
            align-items: center;
            animation: fadeIn 1.5s ease-in-out;
        }
        
        .logo {
            max-width: 100%;
            max-height: 100%;
            width: auto;
            height: auto;
            filter: drop-shadow(0 0 10px rgba(255, 255, 255, 0.1));
            transition: transform 0.3s ease;
        }
        
        .logo:hover {
            transform: scale(1.05);
            filter: drop-shadow(0 0 15px rgba(255, 255, 255, 0.2));
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: scale(0.9);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }
        
        /* Efecto de partículas sutiles de fondo */
        .particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: -1;
        }
        
        .particle {
            position: absolute;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
            animation: float 15s infinite linear;
        }
        
        @keyframes float {
            0% {
                transform: translateY(0) translateX(0);
            }
            100% {
                transform: translateY(-100vh) translateX(100px);
            }
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .logo-container {
                width: 180px;
                height: 180px;
            }
        }
        
        @media (max-width: 480px) {
            .logo-container {
                width: 150px;
                height: 150px;
            }
        }
    </style>
</head>
<body>
    <!-- Partículas de fondo -->
    <div class="particles" id="particles"></div>
    
    <!-- Contenedor del logo -->
    <div class="logo-container">
        <img src="https://echo3dlaser.com.ar/echodata/assets/uploads/tenants/1/logo_1765041795.png" 
             alt="Echo3D Laser Logo" 
             class="logo"
             onerror="this.onerror=null; this.src='data:image/svg+xml;utf8,<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"200\" height=\"200\"><text x=\"50%\" y=\"50%\" dominant-baseline=\"middle\" text-anchor=\"middle\" fill=\"white\" font-size=\"20\">Echo3D Laser</text></svg>'">
    </div>

    <script>
        // Crear partículas de fondo
        function createParticles() {
            const particlesContainer = document.getElementById('particles');
            const particleCount = 30;
            
            for (let i = 0; i < particleCount; i++) {
                const particle = document.createElement('div');
                particle.classList.add('particle');
                
                // Tamaño aleatorio entre 1px y 3px
                const size = Math.random() * 2 + 1;
                particle.style.width = `${size}px`;
                particle.style.height = `${size}px`;
                
                // Posición aleatoria
                particle.style.left = `${Math.random() * 100}%`;
                particle.style.top = `${Math.random() * 100}%`;
                
                // Retraso de animación aleatorio
                particle.style.animationDelay = `${Math.random() * 15}s`;
                
                // Duración de animación aleatoria
                particle.style.animationDuration = `${Math.random() * 10 + 10}s`;
                
                particlesContainer.appendChild(particle);
            }
        }
        
        // Precargar imagen para evitar flash de contenido
        window.addEventListener('load', function() {
            // Crear partículas
            createParticles();
            
            // Precargar imagen
            const img = new Image();
            img.src = 'https://echo3dlaser.com.ar/echodata/assets/uploads/tenants/1/logo_1765041795.png';
            
            // Forzar modo oscuro
            document.documentElement.style.backgroundColor = '#000000';
        });
        
        // Mantener fondo negro incluso durante carga
        document.addEventListener('DOMContentLoaded', function() {
            document.body.style.backgroundColor = '#000000';
        });
    </script>
</body>
</html>