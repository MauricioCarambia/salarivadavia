<form id="formArticulo" enctype="multipart/form-data" class="mb-4">
    <input type="hidden" name="id" id="articulo_id">

    <div class="form-group">
        <label>Título</label>
        <input type="text" name="titulo" id="titulo" class="form-control" required>
    </div>

    <div class="form-group">
        <label>Contenido</label>
        <textarea name="texto" id="texto" class="form-control" rows="4"></textarea>
    </div>

    <div class="form-group">
        <label>Imagen</label>
        <input type="file" name="imagen" id="imagen" class="form-control">
    </div>

    <button class="btn btn-success">Guardar</button>
</form>

<hr>

<table class="table table-bordered" id="tablaArticulos">
    <thead>
        <tr>
            <th>ID</th>
            <th>Título</th>
            <th>Imagen</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody></tbody>
</table>
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
                    <td>${a.titulo}</td>
                    <td>
                        ${a.imagen ? `<img src="uploads/${a.imagen}" width="80">` : ''}
                    </td>
                    <td>
                        <button class="btn btn-warning btn-sm editar" data='${JSON.stringify(a)}'>Editar</button>
                        <button class="btn btn-danger btn-sm eliminar" data-id="${a.id}">Eliminar</button>
                    </td>
                </tr>`;
                });

                $("#tablaArticulos tbody").html(html);
            }, 'json');
        }

        // ======================
        // EDITAR
        // ======================
        $(document).on("click", ".editar", function() {

            let data = $(this).data();

            $("#articulo_id").val(data.id);
            $("#titulo").val(data.titulo);
            $("#texto").val(data.texto);
        });

        // ======================
        // ELIMINAR
        // ======================
        $(document).on("click", ".eliminar", function() {

            let id = $(this).data("id");

            if (!confirm("Eliminar artículo?")) return;

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