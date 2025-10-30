// File Upload Preview - NIB
const nibFile = document.getElementById("nib-file");
const fileList = document.getElementById("file-list");

nibFile.addEventListener("change", function() {
    fileList.innerHTML = "";
    const files = Array.from(nibFile.files);

    if (files.length > 5) {
        alert("Maksimal hanya boleh upload 5 file.");
        nibFile.value = "";
        return;
    }

    for (let file of files) {
        if (file.size > 10 * 1024 * 1024) {
            alert(`File ${file.name} melebihi 10 MB.`);
            nibFile.value = "";
            fileList.innerHTML = "";
            return;
        }
        const li = document.createElement("li");
        li.textContent = `✓ ${file.name} (${(file.size / 1024).toFixed(1)} KB)`;
        li.className = "text-success small";
        fileList.appendChild(li);
    }
});

// File Upload Preview - Foto Produk
const productFile = document.getElementById("product-file");
const productList = document.getElementById("product-list");

productFile.addEventListener("change", function() {
    productList.innerHTML = "";
    const files = Array.from(productFile.files);

    if (files.length > 5) {
        alert("Maksimal hanya boleh upload 5 file.");
        productFile.value = "";
        return;
    }

    for (let file of files) {
        if (file.size > 1 * 1024 * 1024) {
            alert(`File ${file.name} melebihi 1 MB.`);
            productFile.value = "";
            productList.innerHTML = "";
            return;
        }
        const li = document.createElement("li");
        li.textContent = `✓ ${file.name} (${(file.size / 1024).toFixed(1)} KB)`;
        li.className = "text-success small";
        productList.appendChild(li);
    }
});

// File Upload Preview - Foto Proses Produksi
const prosesFile = document.getElementById("prosesproduksi-file");
const packagingList = document.getElementById("packaging-list");

prosesFile.addEventListener("change", function() {
    packagingList.innerHTML = "";
    const files = Array.from(prosesFile.files);

    if (files.length > 5) {
        alert("Maksimal hanya boleh upload 5 file.");
        prosesFile.value = "";
        return;
    }

    for (let file of files) {
        if (file.size > 1 * 1024 * 1024) {
            alert(`File ${file.name} melebihi 1 MB.`);
            prosesFile.value = "";
            packagingList.innerHTML = "";
            return;
        }
        const li = document.createElement("li");
        li.textContent = `✓ ${file.name} (${(file.size / 1024).toFixed(1)} KB)`;
        li.className = "text-success small";
        packagingList.appendChild(li);
    }
});

// Validasi file logo
function validateLogo(fileInput, maxSizeMB = 1) {
    if (fileInput.files.length === 0) return true;
    
    const file = fileInput.files[0];
    if (file.size > maxSizeMB * 1024 * 1024) {
        alert(`File ${file.name} melebihi ${maxSizeMB} MB.`);
        fileInput.value = "";
        return false;
    }
    return true;
}

document.getElementById("logo1-file").addEventListener("change", function() {
    validateLogo(this);
});

document.getElementById("logo2-file").addEventListener("change", function() {
    validateLogo(this);
});

document.getElementById("logo3-file").addEventListener("change", function() {
    validateLogo(this);
});

// Form Submit Handler
document.getElementById('formPendaftaran').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    // Reset semua error
    document.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
    document.querySelectorAll('.invalid-feedback').forEach(el => el.textContent = '');
    
    // Validasi form
    const form = e.target;
    let isValid = true;
    let firstInvalidField = null;
    
    // Validasi field required
    const requiredFields = form.querySelectorAll('[required]');
    requiredFields.forEach(field => {
        if (field.type === 'file') {
            if (field.files.length === 0) {
                field.classList.add('is-invalid');
                const feedback = field.closest('.mb-3').querySelector('.invalid-feedback');
                if (feedback) feedback.textContent = 'File harus diupload';
                isValid = false;
                if (!firstInvalidField) firstInvalidField = field;
            }
        } else if (!field.value.trim()) {
            field.classList.add('is-invalid');
            const feedback = field.closest('.mb-3')?.querySelector('.invalid-feedback') || 
                           field.parentElement.querySelector('.invalid-feedback');
            if (feedback) feedback.textContent = 'Field ini wajib diisi';
            isValid = false;
            if (!firstInvalidField) firstInvalidField = field;
        }
    });
    
    if (!isValid) {
        alert('Mohon lengkapi semua field yang bertanda * (wajib diisi)');
        if (firstInvalidField) {
            firstInvalidField.scrollIntoView({ behavior: 'smooth', block: 'center' });
            firstInvalidField.focus();
        }
        return;
    }
    
    // Konfirmasi sebelum submit
    if (!confirm('Apakah Anda yakin semua data sudah benar dan ingin mengirim pendaftaran?')) {
        return;
    }
    
    // Tampilkan loading
    document.getElementById('loadingOverlay').classList.add('active');
    
    // Siapkan FormData
    const formData = new FormData(form);
    
    try {
        const response = await fetch('process/proses_pendaftaran.php', {
            method: 'POST',
            body: formData
        });
        
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            const text = await response.text();
            console.error('Response bukan JSON:', text);
            throw new Error('Server mengembalikan response yang tidak valid.');
        }
        
        const result = await response.json();
        
        if (result.success) {
            alert('Pendaftaran berhasil dikirim!\n\nData Anda akan segera diproses.');
            window.location.href = 'status-seleksi-pendaftaran.php';
        } else {
            alert('Gagal mengirim pendaftaran.\n\n' + (result.message || 'Terjadi kesalahan'));
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Gagal mengirim pendaftaran.\n\nPesan error: ' + error.message);
    } finally {
        document.getElementById('loadingOverlay').classList.remove('active');
    }
});