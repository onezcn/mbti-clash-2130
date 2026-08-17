(function(win){
  const market=win.onez_market||{};
  win.onez_market||(win.onez_market=market);
  market.post=async function(postdata,url){
    const res=await fetch(url||window.location.href,{
      method:'POST',
      body:JSON.stringify(postdata),
      headers:{
        'Content-Type':'application/json',
      },
    });
    try{
      const json=await res.json();
      return json;
    }catch(e){
      console.error(e);
      return {error:`系统繁忙，请稍后再试`};
    }
  };
})(window);