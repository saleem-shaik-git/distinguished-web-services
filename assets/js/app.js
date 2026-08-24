document.addEventListener('DOMContentLoaded',()=>{
 const nav=document.querySelector('.site-nav');
 const reveals=document.querySelectorAll('.reveal');
 const observer=new IntersectionObserver(entries=>entries.forEach(entry=>{if(entry.isIntersecting)entry.target.classList.add('visible')}),{threshold:.12});
 reveals.forEach(el=>observer.observe(el));
 const onScroll=()=>nav?.classList.toggle('scrolled',window.scrollY>30);
 onScroll(); window.addEventListener('scroll',onScroll,{passive:true});

 // Keep the marketing headline readable on the light background.
 const marketingTitle=document.querySelector('#marketing .section-title');
 if(marketingTitle) marketingTitle.style.color='#101c2c';

 // Link each featured project to its dedicated case study.
 const caseStudies={
   'Digital Banking Platform':'case-studies/digital-banking.php',
   'Logistics Management Platform':'case-studies/logistics-management.php',
   'Talent Management Platform':'case-studies/talent-management.php'
 };
 document.querySelectorAll('.project-card').forEach(card=>{
   const title=card.querySelector('h3')?.textContent.trim();
   const link=card.querySelector('.project-link');
   if(title && link && caseStudies[title]) link.href=caseStudies[title];
 });
});
