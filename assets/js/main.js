// assets/js/main.js — موقع النقب الإخباري

document.addEventListener('DOMContentLoaded', () => {
    
    // 1. Mobile Menu Toggle
    const menuToggle = document.getElementById('mobile-menu-toggle');
    const navLinks = document.getElementById('nav-links');
    
    if (menuToggle && navLinks) {
        menuToggle.addEventListener('click', (e) => {
            e.stopPropagation();
            navLinks.classList.toggle('open');
            const icon = menuToggle.querySelector('i');
            if (icon) {
                if (navLinks.classList.contains('open')) {
                    icon.classList.remove('fa-bars');
                    icon.classList.add('fa-times');
                } else {
                    icon.classList.remove('fa-times');
                    icon.classList.add('fa-bars');
                }
            }
        });

        // Close on outside click
        document.addEventListener('click', (e) => {
            if (!menuToggle.contains(e.target) && !navLinks.contains(e.target)) {
                navLinks.classList.remove('open');
                const icon = menuToggle.querySelector('i');
                if (icon) {
                    icon.classList.remove('fa-times');
                    icon.classList.add('fa-bars');
                }
            }
        });
    }

    // 2. Hero Featured Slider
    const slides = document.querySelectorAll('.hero-slider .slide');
    const dots = document.querySelectorAll('.hero-slider .dot');
    
    if (slides.length > 0) {
        let currentSlide = 0;
        let slideInterval;

        const showSlide = (index) => {
            slides.forEach(s => s.classList.remove('active'));
            dots.forEach(d => d.classList.remove('active'));
            
            if (slides[index]) slides[index].classList.add('active');
            if (dots[index]) dots[index].classList.add('active');
            currentSlide = index;
        };

        const nextSlide = () => {
            let next = (currentSlide + 1) % slides.length;
            showSlide(next);
        };

        const startSlide = () => {
            stopSlide();
            slideInterval = setInterval(nextSlide, 5500);
        };

        const stopSlide = () => {
            if (slideInterval) clearInterval(slideInterval);
        };

        dots.forEach((dot, index) => {
            dot.addEventListener('click', () => {
                showSlide(index);
                startSlide();
            });
        });

        const slider = document.querySelector('.hero-slider');
        if (slider) {
            slider.addEventListener('mouseenter', stopSlide);
            slider.addEventListener('mouseleave', startSlide);
        }

        startSlide();
    }

    // 3. Back to Top Button
    const backToTop = document.getElementById('back-to-top');
    if (backToTop) {
        window.addEventListener('scroll', () => {
            if (window.scrollY > 350) {
                backToTop.classList.add('show');
            } else {
                backToTop.classList.remove('show');
            }
        });

        backToTop.addEventListener('click', () => {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    }

    // 4. Sidebar Radio Mini Player & Animated Equalizer
    const radioAudio = document.getElementById('radio-audio');
    const playBtn = document.getElementById('radio-play-btn');
    const equalizer = document.getElementById('sidebar-equalizer');
    
    if (radioAudio && playBtn) {
        playBtn.addEventListener('click', (e) => {
            e.preventDefault();
            if (radioAudio.paused) {
                radioAudio.play().then(() => {
                    playBtn.innerHTML = '<i class="fas fa-pause"></i>';
                    if (equalizer) equalizer.classList.add('playing');
                }).catch(err => {
                    console.error('Audio play failed:', err);
                });
            } else {
                radioAudio.pause();
                playBtn.innerHTML = '<i class="fas fa-play"></i>';
                if (equalizer) equalizer.classList.remove('playing');
            }
        });

        radioAudio.addEventListener('pause', () => {
            playBtn.innerHTML = '<i class="fas fa-play"></i>';
            if (equalizer) equalizer.classList.remove('playing');
        });

        radioAudio.addEventListener('playing', () => {
            playBtn.innerHTML = '<i class="fas fa-pause"></i>';
            if (equalizer) equalizer.classList.add('playing');
        });
    }

    // 5. Copy Link Action
    const copyBtn = document.getElementById('copy-link-btn');
    if (copyBtn) {
        copyBtn.addEventListener('click', (e) => {
            e.preventDefault();
            const url = copyBtn.getAttribute('data-url') || window.location.href;
            navigator.clipboard.writeText(url).then(() => {
                const originalHtml = copyBtn.innerHTML;
                copyBtn.innerHTML = '<i class="fas fa-check"></i> تم النسخ!';
                copyBtn.style.background = '#16a34a';
                setTimeout(() => {
                    copyBtn.innerHTML = originalHtml;
                    copyBtn.style.background = '';
                }, 2500);
            }).catch(() => {
                alert('تم نسخ الرابط: ' + url);
            });
        });
    }
});
