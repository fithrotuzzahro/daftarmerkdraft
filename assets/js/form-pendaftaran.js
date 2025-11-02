// ===== PRODUK TABLE MANAGEMENT =====
document.getElementById('addProdukRow').addEventListener('click', function () {
    const tbody = document.getElementById('produkTableBody');
    const newRow = document.createElement('tr');
    newRow.innerHTML = `
        <td><input type="text" class="form-control form-control-sm produk-nama" placeholder="Contoh: Minuman Sinom Botol" required></td>
        <td><input type="number" class="form-control form-control-sm produk-jumlah" placeholder="50" min="1" required></td>
        <td><input type="number" class="form-control form-control-sm produk-harga" placeholder="5000" min="0" required></td>
        <td><input type="text" class="form-control form-control-sm produk-omset bg-light" readonly value="Rp 0"></td>
        <td class="text-center">
            <button type="button" class="btn btn-danger btn-sm remove-produk">
                <i class="fas fa-times"></i>
            </button>
        </td>
    `;
    tbody.appendChild(newRow);
    updateRemoveButtons();
    attachCalculationListeners();
});

document.getElementById('produkTableBody').addEventListener('click', function (e) {
    if (e.target.classList.contains('remove-produk') || e.target.parentElement.classList.contains('remove-produk')) {
        const button = e.target.classList.contains('remove-produk') ? e.target : e.target.parentElement;
        button.closest('tr').remove();
        updateRemoveButtons();
        calculateTotalOmset();
    }
});

function updateRemoveButtons() {
    const rows = document.querySelectorAll('#produkTableBody tr');
    rows.forEach((row) => {
        const removeBtn = row.querySelector('.remove-produk');
        removeBtn.disabled = rows.length === 1;
    });
}

function calculateRowOmset(row) {
    const jumlah = parseFloat(row.querySelector('.produk-jumlah').value) || 0;
    const harga = parseFloat(row.querySelector('.produk-harga').value) || 0;
    const omset = jumlah * harga;
    row.querySelector('.produk-omset').value = 'Rp ' + omset.toLocaleString('id-ID');
    calculateTotalOmset();
}

function calculateTotalOmset() {
    const rows = document.querySelectorAll('#produkTableBody tr');
    let total = 0;
    rows.forEach(row => {
        const jumlah = parseFloat(row.querySelector('.produk-jumlah').value) || 0;
        const harga = parseFloat(row.querySelector('.produk-harga').value) || 0;
        total += (jumlah * harga);
    });
    document.getElementById('totalOmset').textContent = 'Rp ' + total.toLocaleString('id-ID');
}

function attachCalculationListeners() {
    const rows = document.querySelectorAll('#produkTableBody tr');
    rows.forEach(row => {
        const jumlahInput = row.querySelector('.produk-jumlah');
        const hargaInput = row.querySelector('.produk-harga');
        jumlahInput.removeEventListener('input', () => calculateRowOmset(row));
        hargaInput.removeEventListener('input', () => calculateRowOmset(row));
        jumlahInput.addEventListener('input', () => calculateRowOmset(row));
        hargaInput.addEventListener('input', () => calculateRowOmset(row));
    });
}

attachCalculationListeners();

// ===== RT/RW FORMATTING =====
document.getElementById('rt_rw').addEventListener('input', function(e) {
    let value = this.value;
    value = value.replace(/[^\d\/]/g, '');
    const slashCount = (value.match(/\//g) || []).length;
    if (slashCount > 1) {
        value = value.substring(0, value.lastIndexOf('/'));
    }
    this.value = value;
});

document.getElementById('rt_rw').addEventListener('blur', function() {
    let value = this.value.trim();
    if (!value) return;
    
    let parts = value.split('/');
    let rt = parts[0] ? parts[0].replace(/\D/g, '') : '';
    let rw = parts[1] ? parts[1].replace(/\D/g, '') : '';
    
    if (rt) {
        rt = rt.substring(0, 3).padStart(3, '0');
        rw = rw ? rw.substring(0, 3).padStart(3, '0') : '001';
        this.value = rt + '/' + rw;
    } else {
        this.value = '';
    }
});

// ===== MODAL PREVIEW SYSTEM =====
function createPreviewModal() {
    if (document.getElementById('filePreviewModal')) {
        return;
    }

    const modalHTML = `
        <div class="modal fade" id="filePreviewModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" style="max-width: 1000px;">
                <div class="modal-content">
                    <div class="modal-header py-2">
                        <h6 class="modal-title mb-0" id="modalFileName">Preview File</h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-2" style="height: 70vh; overflow: auto; background: #f8f9fa;">
                        <div id="modalPreviewContent" class="d-flex align-items-center justify-content-center h-100 p-2">
                            <!-- Content will be inserted here -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;

    document.body.insertAdjacentHTML('beforeend', modalHTML);
}

function showFilePreview(file, fileName) {
    createPreviewModal();
    
    const modal = new bootstrap.Modal(document.getElementById('filePreviewModal'));
    const modalFileName = document.getElementById('modalFileName');
    const modalContent = document.getElementById('modalPreviewContent');
    
    modalFileName.textContent = fileName;
    modalContent.innerHTML = '<div class="spinner-border spinner-border-sm text-primary" role="status"></div>';
    
    modal.show();

    if (file.type === 'application/pdf') {
        const fileURL = URL.createObjectURL(file);
        modalContent.innerHTML = `
            <iframe src="${fileURL}" 
                    class="w-100 h-100 border rounded">
            </iframe>
        `;
    } else if (file.type.startsWith('image/')) {
        const reader = new FileReader();
        reader.onload = function(e) {
            modalContent.innerHTML = `
                <img src="${e.target.result}" 
                     alt="${fileName}" 
                     class="img-fluid rounded" 
                     style="max-width: 100%; max-height: 100%; object-fit: contain;">
            `;
        };
        reader.readAsDataURL(file);
    } else {
        modalContent.innerHTML = `
            <div class="alert alert-warning mb-0 small">
                <i class="fas fa-exclamation-triangle me-1"></i>
                Preview tidak tersedia
            </div>
        `;
    }
}

// ===== FILE STORAGE SYSTEM =====
const fileStorage = {
    'nib_files': [],
    'foto_produk': [],
    'foto_proses': [],
    'logo1': [],
    'logo2': [],
    'logo3': []
};

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
}

function handleFileInput(inputElement, storageKey, previewContainerId, maxFiles = 5) {
    const files = Array.from(inputElement.files);
    
    if (fileStorage[storageKey].length + files.length > maxFiles) {
        alert(`Maksimal ${maxFiles} file untuk ${storageKey.replace(/_/g, ' ')}`);
        inputElement.value = '';
        return;
    }
    
    files.forEach(file => {
        const exists = fileStorage[storageKey].some(f => f.name === file.name && f.size === file.size);
        if (!exists) {
            fileStorage[storageKey].push(file);
        }
    });
    
    updateFilePreview(storageKey, previewContainerId);
    inputElement.value = '';
}

function updateFilePreview(storageKey, containerId) {
    const container = document.getElementById(containerId);
    if (!container) return;
    
    container.innerHTML = '';
    
    if (fileStorage[storageKey].length === 0) {
        container.style.display = 'none';
        return;
    }
    
    container.style.display = 'flex';
    
    fileStorage[storageKey].forEach((file, index) => {
        const previewItem = document.createElement('div');
        previewItem.className = 'preview-item';
        previewItem.style.cssText = 'position: relative; width: 120px; margin: 5px; cursor: pointer;';
        
        // Add click event for preview modal
        previewItem.addEventListener('click', function(e) {
            if (!e.target.closest('.remove-preview')) {
                showFilePreview(file, file.name);
            }
        });
        
        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = (e) => {
                previewItem.innerHTML = `
                    <img src="${e.target.result}" style="width: 120px; height: 120px; object-fit: cover; border-radius: 8px; border: 2px solid #dee2e6; transition: transform 0.2s;">
                    <button type="button" onclick="event.stopPropagation(); removeFile('${storageKey}', ${index}, '${containerId}')" class="remove-preview">×</button>
                    <div style="font-size: 11px; margin-top: 5px; text-align: center; word-break: break-word; color: #495057;">${file.name}</div>
                    <div style="font-size: 10px; text-align: center; color: #6c757d;">${formatFileSize(file.size)}</div>
                `;
                
                // Add hover effect
                const img = previewItem.querySelector('img');
                previewItem.addEventListener('mouseenter', () => {
                    img.style.transform = 'scale(1.05)';
                });
                previewItem.addEventListener('mouseleave', () => {
                    img.style.transform = 'scale(1)';
                });
            };
            reader.readAsDataURL(file);
        } else if (file.type === 'application/pdf') {
            previewItem.innerHTML = `
                <div class="pdf-preview" style="display: flex; flex-direction: column; align-items: center; justify-content: center; width: 120px; height: 120px; border-radius: 8px; border: 2px solid #dee2e6; background: #fff; transition: transform 0.2s;">
                    <i class="fas fa-file-pdf" style="font-size: 3rem; color: #dc3545; margin-bottom: 8px;"></i>
                    <div style="font-size: 10px; text-align: center; padding: 0 5px; color: #495057;">PDF</div>
                </div>
                <button type="button" onclick="event.stopPropagation(); removeFile('${storageKey}', ${index}, '${containerId}')" class="remove-preview">×</button>
                <div style="font-size: 11px; margin-top: 5px; text-align: center; word-break: break-word; color: #495057;">${file.name}</div>
                <div style="font-size: 10px; text-align: center; color: #6c757d;">${formatFileSize(file.size)}</div>
            `;
            
            // Add hover effect for PDF preview
            const pdfPreview = previewItem.querySelector('.pdf-preview');
            previewItem.addEventListener('mouseenter', () => {
                pdfPreview.style.transform = 'scale(1.05)';
            });
            previewItem.addEventListener('mouseleave', () => {
                pdfPreview.style.transform = 'scale(1)';
            });
        }
        
        container.appendChild(previewItem);
    });
}

function removeFile(storageKey, index, containerId) {
    fileStorage[storageKey].splice(index, 1);
    updateFilePreview(storageKey, containerId);
}

function setupDragDropWithStorage(dropZone, fileInput, previewContainer, storageKey, maxFiles = 5, maxSizeMB = 1) {
    if (!dropZone || !fileInput) return;
    
    dropZone.addEventListener('click', () => fileInput.click());
    
    dropZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropZone.classList.add('drag-over');
    });
    
    dropZone.addEventListener('dragleave', () => {
        dropZone.classList.remove('drag-over');
    });
    
    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.classList.remove('drag-over');
        
        const dt = new DataTransfer();
        const droppedFiles = Array.from(e.dataTransfer.files);
        
        if (fileStorage[storageKey].length + droppedFiles.length > maxFiles) {
            alert(`Maksimal ${maxFiles} file. Anda sudah memiliki ${fileStorage[storageKey].length} file.`);
            return;
        }
        
        for (let file of droppedFiles) {
            if (file.size > maxSizeMB * 1024 * 1024) {
                alert(`File ${file.name} melebihi ${maxSizeMB} MB.`);
                return;
            }
            dt.items.add(file);
        }
        
        fileInput.files = dt.files;
        handleFileInput(fileInput, storageKey, previewContainer.id, maxFiles);
    });
    
    fileInput.addEventListener('change', () => {
        const files = Array.from(fileInput.files);
        
        if (fileStorage[storageKey].length + files.length > maxFiles) {
            alert(`Maksimal ${maxFiles} file. Anda sudah memiliki ${fileStorage[storageKey].length} file.`);
            fileInput.value = '';
            return;
        }
        
        for (let file of files) {
            if (file.size > maxSizeMB * 1024 * 1024) {
                alert(`File ${file.name} melebihi ${maxSizeMB} MB.`);
                fileInput.value = '';
                return;
            }
        }
        
        handleFileInput(fileInput, storageKey, previewContainer.id, maxFiles);
    });
}

// ===== LEGALITAS SYSTEM (COMPLETELY REWRITTEN) =====
function updateLegalitasUploads() {
    const legalitasCheckboxes = document.querySelectorAll('.legalitas-checkbox');
    const legalitasContainer = document.getElementById('legalitasUploadContainer');
    const legalitasLainInput = document.querySelector('input[name="legalitas_lain"]');
    
    legalitasContainer.innerHTML = '';
    
    // Handle checkbox legalitas
    legalitasCheckboxes.forEach((checkbox, realIndex) => {
        if (checkbox.checked) {
            const legalitasName = checkbox.value;
            const storageKey = `legalitas_${realIndex}`;
            
            if (!fileStorage[storageKey]) {
                fileStorage[storageKey] = [];
            }
            
            const uploadSection = document.createElement('div');
            uploadSection.className = 'legalitas-upload-section';
            uploadSection.innerHTML = `
                <h6><i class="fas fa-file-upload me-2"></i>Upload File ${legalitasName}</h6>
                <div class="file-drop-zone legalitas-drop-zone" id="legalitas-drop-${realIndex}">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <p><strong>Seret & Lepas file di sini</strong><br>atau klik untuk memilih file</p>
                    <small>Upload maksimal 3 file PDF. Maks 1 MB per file</small>
                    <input type="file" 
                           name="legalitas_files_${realIndex}[]" 
                           id="legalitas-input-${realIndex}" 
                           class="legalitas-file-input" 
                           accept=".pdf" 
                           multiple 
                           hidden>
                </div>
                <div class="preview-container" id="legalitas-preview-${realIndex}"></div>
            `;
            legalitasContainer.appendChild(uploadSection);
            
            setTimeout(() => {
                setupDragDropWithStorage(
                    document.getElementById(`legalitas-drop-${realIndex}`),
                    document.getElementById(`legalitas-input-${realIndex}`),
                    document.getElementById(`legalitas-preview-${realIndex}`),
                    storageKey, 3, 1
                );
            }, 100);
        }
    });
    
    // Handle legalitas lainnya
    if (legalitasLainInput && legalitasLainInput.value.trim() !== '') {
        const storageKey = 'legalitas_lain';
        
        if (!fileStorage[storageKey]) {
            fileStorage[storageKey] = [];
        }
        
        const uploadSection = document.createElement('div');
        uploadSection.className = 'legalitas-upload-section';
        uploadSection.innerHTML = `
            <h6><i class="fas fa-file-upload me-2"></i>Upload File ${legalitasLainInput.value}</h6>
            <div class="file-drop-zone legalitas-drop-zone" id="legalitas-drop-lain">
                <i class="fas fa-cloud-upload-alt"></i>
                <p><strong>Seret & Lepas file di sini</strong><br>atau klik untuk memilih file</p>
                <small>Upload maksimal 3 file (PDF. Maks 1 MB per file</small>
                <input type="file" 
                       name="legalitas_files_lain[]" 
                       id="legalitas-input-lain" 
                       class="legalitas-file-input" 
                       accept=".pdf" 
                       multiple 
                       hidden>
            </div>
            <div class="preview-container" id="legalitas-preview-lain"></div>
        `;
        legalitasContainer.appendChild(uploadSection);
        
        setTimeout(() => {
            setupDragDropWithStorage(
                document.getElementById('legalitas-drop-lain'),
                document.getElementById('legalitas-input-lain'),
                document.getElementById('legalitas-preview-lain'),
                storageKey, 3, 1
            );
        }, 100);
    }
}

const legalitasLainInput = document.querySelector('input[name="legalitas_lain"]');
if (legalitasLainInput) {
    legalitasLainInput.addEventListener('input', function () {
        clearTimeout(window.legalitasLainTimeout);
        window.legalitasLainTimeout = setTimeout(() => {
            updateLegalitasUploads();
        }, 500);
    });
}

// ===== FORM SUBMISSION =====
function handleFormSubmit(e) {
    e.preventDefault();
    
    console.log('=== FORM SUBMISSION STARTED ===');
    
    // 1. Validasi produk
    const rows = document.querySelectorAll('#produkTableBody tr');
    const produkData = [];
    
    rows.forEach(row => {
        const nama = row.querySelector('.produk-nama').value.trim();
        const jumlah = parseInt(row.querySelector('.produk-jumlah').value) || 0;
        const harga = parseInt(row.querySelector('.produk-harga').value) || 0;
        
        if (nama && jumlah > 0 && harga > 0) {
            produkData.push({
                nama,
                jumlah,
                harga,
                omset: jumlah * harga,
                kapasitas: jumlah + ' unit'
            });
        }
    });
    
    if (produkData.length === 0) {
        alert('Mohon isi minimal 1 data produk dengan lengkap!');
        return false;
    }
    
    document.getElementById('produkData').value = JSON.stringify(produkData);
    
    // 2. Validasi file wajib
    const requiredFiles = {
        'nib_files': 'File NIB',
        'foto_produk': 'Foto Produk',
        'foto_proses': 'Foto Proses Produksi',
        'logo1': 'Logo Merek Alternatif 1',
        'logo2': 'Logo Merek Alternatif 2',
        'logo3': 'Logo Merek Alternatif 3'
    };
    
    for (let [key, label] of Object.entries(requiredFiles)) {
        if (!fileStorage[key] || fileStorage[key].length === 0) {
            alert(`${label} wajib diupload!`);
            return false;
        }
    }
    
    console.log('File storage keys:', Object.keys(fileStorage).filter(k => fileStorage[k].length > 0));
    
    // 3. Transfer files ke input form
    Object.keys(fileStorage).forEach(storageKey => {
        if (fileStorage[storageKey] && fileStorage[storageKey].length > 0) {
            let inputName;
            
            // Tentukan nama input berdasarkan storage key
            if (storageKey.startsWith('logo')) {
                inputName = storageKey; // logo1, logo2, logo3
            } else if (storageKey.startsWith('legalitas_')) {
                if (storageKey === 'legalitas_lain') {
                    inputName = 'legalitas_files_lain[]';
                } else {
                    // legalitas_0, legalitas_1, dst
                    const index = storageKey.split('_')[1];
                    inputName = `legalitas_files_${index}[]`;
                }
            } else {
                inputName = `${storageKey}[]`; // nib_files[], foto_produk[], foto_proses[]
            }
            
            const input = document.querySelector(`input[name="${inputName}"]`);
            
            if (input) {
                const dataTransfer = new DataTransfer();
                fileStorage[storageKey].forEach(file => {
                    dataTransfer.items.add(file);
                });
                input.files = dataTransfer.files;
                console.log(`✓ Transferred ${fileStorage[storageKey].length} files to ${inputName}`);
            } else {
                console.warn(`✗ Input not found: ${inputName}`);
            }
        }
    });
    
    // 4. Konfirmasi
    const confirmed = confirm(
        'Apakah Anda yakin semua data yang diisi sudah benar dan lengkap?\n\n' +
        'Setiap akun hanya dapat melakukan 1 kali pendaftaran merek.\n\n' +
        'Data yang sudah dikirim tidak dapat diubah.'
    );
    
    if (!confirmed) {
        console.log('User cancelled submission');
        return false;
    }
    
    // 5. Submit
    const btnSubmit = document.getElementById('btnSubmit');
    btnSubmit.disabled = true;
    btnSubmit.innerHTML = '<i class="fas fa-spinner fa-spin pe-2"></i> Mengirim Data...';
    window.formChanged = false;
    
    console.log('=== SUBMITTING FORM ===');
    e.target.submit();
}

// ===== INITIALIZE =====
document.addEventListener('DOMContentLoaded', function () {
    console.log('Initializing form...');
    
    // Setup file uploads
    setupDragDropWithStorage(
        document.getElementById('nibDropZone'),
        document.getElementById('nib-file'),
        document.getElementById('nibPreview'),
        'nib_files', 5, 10
    );
    
    setupDragDropWithStorage(
        document.getElementById('produkDropZone'),
        document.getElementById('product-file'),
        document.getElementById('produkPreview'),
        'foto_produk', 5, 1
    );
    
    setupDragDropWithStorage(
        document.getElementById('prosesDropZone'),
        document.getElementById('prosesproduksi-file'),
        document.getElementById('prosesPreview'),
        'foto_proses', 5, 1
    );
    
    setupDragDropWithStorage(
        document.getElementById('logo1DropZone'),
        document.getElementById('logo1-file'),
        document.getElementById('logo1Preview'),
        'logo1', 1, 1
    );
    
    setupDragDropWithStorage(
        document.getElementById('logo2DropZone'),
        document.getElementById('logo2-file'),
        document.getElementById('logo2Preview'),
        'logo2', 1, 1
    );
    
    setupDragDropWithStorage(
        document.getElementById('logo3DropZone'),
        document.getElementById('logo3-file'),
        document.getElementById('logo3Preview'),
        'logo3', 1, 1
    );
    
    // Setup legalitas
    const legalitasCheckboxes = document.querySelectorAll('.legalitas-checkbox');
    legalitasCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateLegalitasUploads);
    });
    
    // Setup form submit
    const form = document.getElementById('formPendaftaran');
    if (form) {
        form.addEventListener('submit', handleFormSubmit);
    }
    
    // Warning saat keluar
    window.formChanged = false;
    document.querySelectorAll('#formPendaftaran input, #formPendaftaran textarea, #formPendaftaran select').forEach(element => {
        element.addEventListener('change', function () {
            window.formChanged = true;
        });
    });
    
    window.addEventListener('beforeunload', function (e) {
        if (window.formChanged) {
            e.preventDefault();
            e.returnValue = '';
            return '';
        }
    });
    
    console.log('Form initialized successfully');
});
// ===== KELURAHAN/DESA DATA =====
const desaKelurahan = {
    "Sidoarjo": ["Sidoarjo", "Lemahputro", "Magersari", "Gebang", "Celep", "Bulusidokare", "Urangagung", "Banjarbendo", "Blurukidul", "Cemengbakalan", "Jati", "Kemiri", "Lebo", "Rangkalkidul", "Sarirogo", "Suko", "Sumput", "Cemengkalan", "Pekawuman", "Pucang", "Pucanganom", "Sekardangan", "Sidoklumpuk", "Sidokumpul"],
    "Buduran": ["Buduran", "Sawohan", "Siwalanpanji", "Prasung", "Banjarkemantren", "Banjarsari", "Damarsi", "Dukuhtengah", "Entalsewu", "Pagerwojo", "Sidokerto", "Sidomulyo", "Sidokepung", "Sukorejo", "Wadungasin"],
    "Candi": ["Candi", "Durungbanjar", "Larangan", "Sumokali", "Sepande", "Kebonsari", "Kedensari", "Bligo", "Balongdowo", "Balonggabus", "Durungbanjar", "Durungbedug", "Gelam", "Jambangan", "Kalipecabean", "Karangtanjung", "Kebonsari", "Kedungkendo", "Kedungpeluk", "Kendalpencabean", "Klurak", "Ngampelsari", "Sidodadi", "Sugihwaras", "Sumorame", "Tenggulunan", "Wedoroklurak"],
    "Porong": ["Porong", "Kebonagung", "Kesambi", "Plumbon", "Pesawahan", "Gedang", "Juwetkenongo", "Kedungboto", "Wunut", "Pamotan", "Kebakalan", "Gempol Pasargi", "Glagaharum", "Lajuk", "Candipari"],
    "Krembung": ["Krembung", "Balanggarut", "Cangkring", "Gading", "Jenggot", "Kandangan", "Kedungrawan", "Kedungsumur", "Keperkeret", "Lemujut", "Ploso", "Rejeni", "Tambakrejo", "Tanjekwagir", "Wangkal", "Wonomlati", "Waung", "Mojoruntut"],
    "Tulangan": ["Tulangan", "Jiken", "Kajeksan", "Kebaran", "Kedondong", "Kepatihan", "Kepunten", "Medalem", "Pangkemiri", "Sudimoro", "Tlasih", "Gelang", "Kepadangan", "Grabagan", "Singopadu", "Kemantren", "Janti", "Modong", "Grogol", "Kenongo", "Grinting"],
    "Tanggulangin": ["kalisampurno", "kedensari", "Ganggang Pnjang", "Randegan", "Kalitengah", "Kedung Banteng", "Putat", "Ketapang", "Kalidawir", "Ketegan", "Banjar Panji", "Gempolsari", "Sentul", "Penatarsewu", "Banjarsari", "Ngaban", "Boro", "Kludan"],
    "Jabon": ["Trompoasri", "Kedung Pandan", "Permisan", "Semambung", "Pangrih", "Kupang", "Tambak Kalisogo", "Kedungrejo", "Kedungcangkring", "Keboguyang", "Jemirahan", "Balongtani", "dukuhsari"],
    "Krian": ["Sidomojo", "Sidomulyo", "Sidorejo", "Tempel", "Terik", "Terungkulon", "Terungwetan", "Tropodo", "Watugolong", "Krian", "Kemasan", "Tambakkemeraan", "Sedenganmijen", "Bareng Krajan", "Keraton", "Keboharan", "Katerungan", "Jeruk Gamping", "Junwangi", "Jatikalang", "Gamping", "Ponokawan"],
    "Balongbendo": ["Balongbendo", "", "WonoKupang", "Kedungsukodani", "Kemangsen", "Penambangan", "Seduri", "Seketi", "Singkalan", "SumoKembangsri", "Waruberon", "Watesari", "Wonokarang", "Jeruklegi", "Jabaran", "Suwaluh", "Gadungkepuhsari", "Bogempinggir", "Bakungtemenggungan", "Bakungpringgodani", "Wringinpitu", "Bakalan"],
    "Wonoayu": ["Becirongengor", "Candinegoro", "Jimbaran Kulon", "Jimbaran wetan", "Pilang", "Karangturi", "Ketimang", "Lambangan", "Mohorangagung", "Mulyodadi", "Pagerngumbuk", "Plaosan", "Ploso", "Popoh", "Sawocangkring", "semambung", "Simoangin-angin", "Simoketawang", "Sumberejo", "Tanggul", "Wonoayu", "Wonokalang", "Wonokasian"],
    "Tarik": ["Tarik", "Klantingsari", "GedangKlutuk", "Mergosari", "Kedinding", "Kemuning", "Janti", "Mergobener", "Mliriprowo", "Singogalih", "Kramat Temenggung", "Kedungbocok", "Segodobancang", "Gampingrowo", "Mindugading", "Kalimati", "Banjarwungu", "Balongmacekan", "Kendalsewu", "Sebani"],
    "Prambon": ["Prambon", "Bendotretek", "Bulang", "Cangkringturi", "Gampang", "Gedangrowo", "Jati alun-alun", "Watutulis", "jatikalang", "jedongcangkring", "Kajartengguli", "Kedungkembanr", "Kedung Sugo", "Kedungwonokerto", "Penjangkkungan", "Simogirang", "Simpang", "Temu", "Wirobiting", "Wonoplintahan"],
    "Taman": ["Taman", "Trosobo", "Sepanjang", "Ngelom", "Ketegan", "Jemundo", "Geluran", "Wage", "Bebekan", "Kalijaten", "Tawangsari", "Sidodadi", "Sambibulu", "Sadang", "Maduretno", "Krembangan", "Pertapan", "Kramatjegu", "Kletek", "Tanjungsari", "Kedungturi", "Gilang", "Bringinbendo", "Bohar", "Wonocolo"],
    "Waru": ["Waru", "Tropodo", "Kureksari", "Jambangan", "Medaeng", "Berbek", "Bungurasih", "Janti", "Kedungrejo", "Kepuhkiriman", "Ngingas", "Pepelegi", "Tambakoso", "Tambakrejo", "Tambahsawah", "Tambaksumur", "Wadungasri", "Wedoro"],
    "Gedangan": ["Gedangan", "Ketajen", "Wedi", "Bangah", "Sawotratap", "Semambung", "Ganting", "Tebel", "Kebonanom", "Gemurung", "Karangbong", "Kebiansikep", "Kragan", "Punggul", "Seruni"],
    "Sedati": ["Sedati", "Pabean", "Semampir", "Banjarkemuningtambak", "Pulungan", "Betro", "Segoro Tambak", "Gisik Cemandi", "Cemandi", "Kalanganyar", "Buncitan", "Wangsan", "Pranti", "Pepe", "Sedatiagung", "Sedatigede", "Tambakcemandi"],
    "Sukodono": ["Sukodono", "Jumputrejo", "Kebonagung", "Keloposepuluh", "Jogosatru", "Suruh", "Ngaresrejo", "Cangkringsari", "Masangan Wetan", "Masangan Kulon", "Bangsri", "Anggaswangi", "Pandemonegoro", "Panjunan", "Pekarungan", "Plumbungan", "Sambungrejo", "Suko", "Wilayut"]
};

document.getElementById('kecamatan').addEventListener('change', function () {
    const kecamatan = this.value;
    const kelDesaSelect = document.getElementById('kel_desa');
    kelDesaSelect.innerHTML = '<option value="">-- Pilih Kelurahan/Desa --</option>';
    
    if (kecamatan && desaKelurahan[kecamatan]) {
        desaKelurahan[kecamatan].forEach(function (desa) {
            const option = document.createElement('option');
            option.value = desa;
            option.textContent = desa;
            kelDesaSelect.appendChild(option);
        });
        kelDesaSelect.disabled = false;
    } else {
        kelDesaSelect.disabled = true;
    }
});