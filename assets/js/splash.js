// Auto redirect to login after animation
        setTimeout(() => {
            const container = document.getElementById('splashContainer');
            container.classList.add('fade-out');
            
            setTimeout(() => {
                window.location.href = 'login.php';
            }, 800);
        }, 4500); // Total animation time: 4.5 seconds

        // Preload login page resources
        const link = document.createElement('link');
        link.rel = 'prefetch';
        link.href = 'login.php';
        document.head.appendChild(link);
        
        // Preload login CSS
        const cssLink = document.createElement('link');
        cssLink.rel = 'prefetch';
        cssLink.href = 'assets/css/login.css';
        document.head.appendChild(cssLink);