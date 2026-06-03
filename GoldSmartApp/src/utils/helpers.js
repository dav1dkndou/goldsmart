export const formatCurrency = (amount) => {
  if (amount == null) return 'Rp 0';
  const num = Number(amount);
  return 'Rp ' + num.toFixed(0).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
};

export const formatGC = (amount) => {
  if (amount == null) return '0 GC';
  const num = Number(amount);
  return (Number.isInteger(num) ? num.toString() : num.toFixed(2)) + ' GC';
};

export const formatDate = (dateString) => {
  if (!dateString) return '';
  const d = new Date(dateString);
  const pad = (n) => n.toString().padStart(2, '0');
  return `${pad(d.getDate())}/${pad(d.getMonth() + 1)}/${d.getFullYear()} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
};

export const getStatusColor = (status) => {
  const colors = {
    pending: '#F59E0B',
    processing: '#3B82F6',
    completed: '#10B981',
    approved: '#10B981',
    success: '#10B981',
    cancelled: '#EF4444',
    rejected: '#EF4444',
    failed: '#EF4444',
    expired: '#6B7280',
  };
  return colors[status?.toLowerCase()] || '#6B7280';
};

export const getStatusLabel = (status) => {
  const labels = {
    pending: 'Menunggu',
    processing: 'Diproses',
    completed: 'Selesai',
    approved: 'Disetujui',
    success: 'Berhasil',
    cancelled: 'Dibatalkan',
    rejected: 'Ditolak',
    failed: 'Gagal',
    expired: 'Kedaluwarsa',
  };
  return labels[status?.toLowerCase()] || status || '';
};

export const getFullUrl = (path) => {
  if (!path) return null;
  if (path.startsWith('http')) return path;
  const cleanPath = path.startsWith('/') ? path.slice(1) : path;
  return `https://goldsmart.online/${cleanPath}`;
};
