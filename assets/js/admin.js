document.addEventListener('DOMContentLoaded', function() {
    
    // Sidebar toggle
    const toggleSidebar = document.getElementById('toggleSidebar');
    const closeSidebar = document.getElementById('closeSidebar');
    const sidebar = document.getElementById('sidebar');
    
    if (toggleSidebar && sidebar) {
        toggleSidebar.addEventListener('click', function() {
            sidebar.classList.add('open');
        });
    }
    
    if (closeSidebar && sidebar) {
        closeSidebar.addEventListener('click', function() {
            sidebar.classList.remove('open');
        });
    }
    
    // Delete article
    const deleteButtons = document.querySelectorAll('.delete-article-btn');
    if (deleteButtons.length > 0) {
        deleteButtons.forEach(btn => {
            btn.addEventListener('click', function() {
                if (confirm('هل أنت متأكد من حذف هذا المقال؟ لا يمكن التراجع عن هذا الإجراء.')) {
                    const id = this.getAttribute('data-id');
                    const row = document.getElementById('row-' + id);
                    
                    const formData = new FormData();
                    formData.append('id', id);
                    if (typeof csrfTokenStr !== 'undefined') {
                        formData.append('csrf_token', csrfTokenStr);
                    }
                    
                    fetch('ajax/delete-article.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            if (row) {
                                row.style.transition = 'opacity 0.3s';
                                row.style.opacity = '0';
                                setTimeout(() => row.remove(), 300);
                            }
                            showToast(data.message, 'success');
                        } else {
                            showToast(data.message || 'حدث خطأ', 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showToast('حدث خطأ في الاتصال', 'error');
                    });
                }
            });
        });
    }
});

// Toast notification system
function showToast(message, type = 'success') {
    let container = document.querySelector('.toast-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container';
        document.body.appendChild(container);
    }
    
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerHTML = `
        <span>${message}</span>
        <button style="background:none;border:none;color:inherit;cursor:pointer;font-size:1.2rem;margin-right:10px;">&times;</button>
    `;
    
    const closeBtn = toast.querySelector('button');
    closeBtn.addEventListener('click', () => {
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 300);
    });
    
    container.appendChild(toast);
    
    setTimeout(() => {
        if (toast.parentNode) {
            toast.style.opacity = '0';
            setTimeout(() => toast.remove(), 300);
        }
    }, 5000);
}
