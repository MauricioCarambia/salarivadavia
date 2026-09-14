<style>
    .card-custom {
        border: 0;
        border-radius: 14px;
        box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
    }

    .card-header-custom {
        background: linear-gradient(135deg, #007bff, #0056b3);
        color: white;
        border-radius: 14px 14px 0 0;
        padding: 15px 20px;
    }

    .table thead th {
        background: #343a40;
        color: white;
        vertical-align: middle;
    }

    .articulo-texto {
        width: 500px;
        max-width: 500px;
        white-space: normal;
        word-break: break-word;
        line-height: 1.5;
    }

    .preview-img {
        width: 120px;
        height: 80px;
        object-fit: cover;
        border-radius: 10px;
        border: 1px solid #ddd;
    }

    .btn-action {
        min-width: 85px;
    }

    .table td {
        vertical-align: middle;
    }

    .form-control {
        border-radius: 10px;
    }

    textarea.form-control {
        resize: vertical;
    }

    .btn-success {
        border-radius: 10px;
        padding: 10px 25px;
        font-weight: 600;
    }
</style>

<!-- =========================
        FORMULARIO
========================= -->
  <div class="card card-info card-outline">

     <div class="card-body">

        <form id="formArticulo" enctype="multipart/form-data">

            <input type="hidden" name="id" id="articulo_id">

            <div class="row">

                <!-- TITULO -->
                <div class="col-md-8">
                    <div class="form-group">
                        <label><strong>Título</strong></label>

                        <input type="text"
                            name="titulo"
                            id="titulo"
                            class="form-control"
                            required>
                    </div>
                </div>

                <!-- IMAGEN -->
                <div class="col-md-4">
                    <div class="form-group">
                        <label><strong>Imagen</strong></label>

                        <input type="file"
                            name="imagen"
                            id="imagen"
                            class="form-control">
                    </div>
                </div>

                <!-- CONTENIDO -->
                <div class="col-12">
                    <div class="form-group">
                        <label><strong>Contenido</strong></label>

                        <textarea name="texto"
                            id="texto"
                            class="form-control"
                            rows="10"></textarea>
                    </div>
                </div>

                <div class="col-12 mt-2">
                    <button class="btn btn-success">
                        <i class="fas fa-save"></i>
                        Guardar Artículo
                    </button>
                </div>

            </div>

        </form>

    </div>

</div>

<!-- =========================
        TABLA
========================= -->
  <div class="card card-info card-outline">

    <div class="card-body table-responsive">

        <table class="table table-bordered table-hover" id="tablaArticulos">

            <thead>
                <tr>
                    <th width="60">ID</th>
                    <th width="220">Título</th>
                    <th>Contenido</th>
                    <th width="150">Imagen</th>
                    <th width="180">Acciones</th>
                </tr>
            </thead>

            <tbody></tbody>

        </table>

    </div>

</div>

<script>
    $(document).ready(function() {

        cargarArticulos();

        // ======================
        // GUARDAR / EDITAR
        // ======================
        $("#formArticulo").submit(function(e) {

            e.preventDefault();

            let formData = new FormData(this);

            $.ajax({
                url: "ajax/articulos_guardar.php",
                type: "POST",
                data: formData,
                contentType: false,
                processData: false,
                dataType: "json",

                success: function(res) {

                    if (res.success) {

                        $("#formArticulo")[0].reset();

                        $("#articulo_id").val('');

                        cargarArticulos();

                    } else {

                        alert(res.message);

                    }
                }
            });
        });

        // ======================
        // CARGAR
        // ======================
        function cargarArticulos() {

            $.get("ajax/articulos_listar.php", function(data) {

                let html = '';

                data.forEach(a => {

                    html += `
<tr>

    <td>${a.id}</td>

    <td>
        <strong>${a.titulo}</strong>
    </td>

    <td class="articulo-texto">
        ${a.texto}
    </td>

    <td class="text-center">

        ${a.imagen 
            ? `<img src="uploads/${a.imagen}" class="preview-img">`
            : '<span class="text-muted">Sin imagen</span>'
        }

    </td>

    <td class="text-center">

        <button 
            class="btn btn-warning btn-sm editar btn-action"
            data-id="${a.id}"
            data-titulo="${a.titulo}"
            data-texto="${encodeURIComponent(a.texto)}"
        >
            <i class="fas fa-edit"></i>
            Editar
        </button>

        <button 
            class="btn btn-danger btn-sm eliminar btn-action mt-1"
            data-id="${a.id}"
        >
            <i class="fas fa-trash"></i>
            Eliminar
        </button>

    </td>

</tr>
`;

                });

                $("#tablaArticulos tbody").html(html);

            }, 'json');
        }

        // ======================
        // EDITAR
        // ======================
        $(document).on("click", ".editar", function() {

            $("#articulo_id").val($(this).data("id"));

            $("#titulo").val($(this).data("titulo"));

            $("#texto").val(
                decodeURIComponent($(this).data("texto"))
            );

            $('html, body').animate({
                scrollTop: 0
            }, 400);

        });

        // ======================
        // ELIMINAR
        // ======================
        $(document).on("click", ".eliminar", function() {

            let id = $(this).data("id");

            if (!confirm("¿Eliminar artículo?")) return;

            $.post("ajax/articulos_eliminar.php", {
                id
            }, function(res) {

                if (res.success) {

                    cargarArticulos();

                } else {

                    alert(res.message);

                }

            }, 'json');

        });

    });
</script>