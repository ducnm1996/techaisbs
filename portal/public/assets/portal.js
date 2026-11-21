(() => {
 const form=document.querySelector('[data-reset-form]');
 if(!form)return;
 const token=new URLSearchParams(location.hash.slice(1)).get('token');
 if(token){document.querySelector('#reset-token').value=token;history.replaceState(null,'',location.pathname+location.search);}
})();
