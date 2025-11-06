<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restablecer contraseña</title>
    <style>
        body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Noto Sans,sans-serif;background:#f8fafc;margin:0;}
        .container{max-width:480px;margin:40px auto;background:#fff;border-radius:12px;box-shadow:0 10px 25px rgba(2,6,23,0.08);padding:24px;}
        h1{font-size:20px;margin:0 0 12px;color:#111827}
        p{color:#4b5563;margin:0 0 16px}
        label{display:block;font-weight:600;color:#374151;margin-bottom:6px}
        input{width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:14px}
        input[readonly]{background:#f3f4f6;color:#6b7280}
        .row{margin-bottom:14px}
        .btn{display:inline-block;background:#2563eb;color:#fff;border:none;border-radius:8px;padding:10px 14px;font-weight:600;cursor:pointer}
        .btn:disabled{opacity:.6;cursor:not-allowed}
        .alert{padding:10px 12px;border-radius:8px;margin:12px 0;font-size:14px}
        .alert-success{background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0}
        .alert-error{background:#fef2f2;color:#7f1d1d;border:1px solid #fecaca}
        .note{font-size:12px;color:#6b7280;margin-top:8px}
    </style>
    <script>
        function getQueryParam(name){
            const params=new URLSearchParams(window.location.search);return params.get(name)||'';
        }
        document.addEventListener('DOMContentLoaded',()=>{
            const emailInput=document.getElementById('email');
            const tokenInput=document.getElementById('token');
            emailInput.value=getQueryParam('email');
            tokenInput.value=getQueryParam('token');
        });
    </script>
    </head>
<body>
    <div class="container">
        <h1>Restablecer contraseña</h1>
        <p>Ingresa tu nueva contraseña para completar el proceso. El enlace de restablecimiento expira en 24 horas.</p>

        <form id="resetForm" method="post" action="{{ route('password.reset.submit') }}">
            @csrf
            <div class="row">
                <label for="email">Correo</label>
                <input id="email" name="email" type="email" readonly>
            </div>
            <div class="row">
                <label for="token">Token</label>
                <input id="token" name="token" type="text" readonly>
            </div>
            <div class="row">
                <label for="password">Nueva contraseña</label>
                <input id="password" name="password" type="password" minlength="8" required>
            </div>
            <div class="row">
                <label for="password_confirmation">Confirmar contraseña</label>
                <input id="password_confirmation" name="password_confirmation" type="password" minlength="8" required>
            </div>
            <button class="btn" type="submit">Actualizar contraseña</button>
        </form>

        <div id="message" style="display:none"></div>

        <p class="note">Si tienes problemas con el enlace, solicita un nuevo correo de restablecimiento.</p>
    </div>

    <script>
        const form=document.getElementById('resetForm');
        const msg=document.getElementById('message');
        form.addEventListener('submit',async(e)=>{
            e.preventDefault();
            msg.style.display='none';
            const submitBtn=form.querySelector('button[type="submit"]');
            submitBtn.disabled=true;
            try{
                const formData=new FormData(form);
                const payload={
                    email: formData.get('email'),
                    token: formData.get('token'),
                    password: formData.get('password'),
                    password_confirmation: formData.get('password_confirmation'),
                };
                const res=await fetch('{{ url('/api/reset-password') }}',{
                    method:'POST',
                    headers:{'Content-Type':'application/json'},
                    body:JSON.stringify(payload)
                });
                const data=await res.json();
                msg.className='alert '+(res.ok?'alert-success':'alert-error');
                msg.textContent=data.message||'Operación completada.';
                msg.style.display='block';
                if(res.ok){
                    form.reset();
                }
            }catch(err){
                msg.className='alert alert-error';
                msg.textContent='Error inesperado. Inténtalo de nuevo.';
                msg.style.display='block';
            }finally{
                submitBtn.disabled=false;
            }
        });
    </script>
</body>
</html>