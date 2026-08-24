document.addEventListener('DOMContentLoaded',()=>{
 const nav=document.querySelector('.site-nav');
 const reveals=document.querySelectorAll('.reveal');
 const observer=new IntersectionObserver(entries=>entries.forEach(entry=>{if(entry.isIntersecting)entry.target.classList.add('visible')}),{threshold:.12});
 reveals.forEach(el=>observer.observe(el));
 const onScroll=()=>nav?.classList.toggle('scrolled',window.scrollY>30);
 onScroll(); window.addEventListener('scroll',onScroll,{passive:true});
});
