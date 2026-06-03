        </div>
    </main>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script>
        'use strict';
        
        // Initialize DataTables
        $(() => {
            $('.datatable').DataTable({
                language: {
                    search: "Cari:",
                    lengthMenu: "Tampilkan _MENU_ data",
                    info: "Menampilkan _START_ - _END_ dari _TOTAL_ data",
                    infoEmpty: "Tidak ada data",
                    infoFiltered: "(difilter dari _MAX_ total data)",
                    zeroRecords: "Tidak ada data yang cocok",
                    paginate: {
                        first: "Pertama",
                        last: "Terakhir",
                        next: "Selanjutnya",
                        previous: "Sebelumnya"
                    }
                },
                pageLength: 10,
                responsive: true,
                lengthMenu: [[5, 15, 25, 50, 100], [5, 15, 25, 50, 100]],
                order: [[9, 'desc']]
            });
        });
        
        // Toggle Sidebar (Mobile)
        const toggleSidebar = () => {
            document.getElementById('sidebar').classList.toggle('show');
            document.getElementById('sidebarOverlay').classList.toggle('show');
        };
        
        // Confirm Delete
        const confirmDelete = (message) => {
            return confirm(message || 'Apakah Anda yakin ingin menghapus?');
        };
        
        // Auto hide alerts
        setTimeout(() => {
            document.querySelectorAll('.alert-dismissible').forEach((alert) => {
                new bootstrap.Alert(alert).close();
            });
        }, 5000);
    </script>
</body>
</html>
