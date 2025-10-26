<!-- JS -->
<script src="../../assets/js/core/jquery-3.7.1.min.js"></script>
<script src="../../assets/js/core/popper.min.js"></script>
<script src="../../assets/js/core/bootstrap.min.js"></script>
<!-- Datatables -->
<script src="../../assets/js/plugin/datatables/datatables.min.js"></script>
<script src="../../assets/js/kaiadmin.min.js"></script>
<script>
    $(document).ready(function() {
        $("#datatable-table").DataTable({
            pageLength: 10
        });
    });
</script>