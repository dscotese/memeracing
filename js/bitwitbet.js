function validate_proposal(frm)
{
    if(frm.prompt.length < 4)
    {
        alert("That's an awfully small prompt!");
        return false;
    }
    return true;
}

function validate_answer(frm)
{
    if(frm.answer.length < 4)
    {
        alert("That's an awfully small answer!");
        return false;
    }
    if(!frm.email.value.match(/^[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,4}$/i))
    {
        alert("We need an email address.");
        return false;
    }
    return true;
}

$(document).ready(function()
{
    if(opener)
    {
        window.setTimeout('opener.proceed()',100);
    }
    $("textarea,input[type='email'],input[type='search'],input[type='text']").each(function()
    {
        this.inx = this.value;
        this.onfocus=function() 
        {
            this.value = (this.value == this.inx) ? '' : this.value;
        };
        this.onblur=function()
        {
            this.value = (this.value == '') ? this.inx : this.value;
        }
    } );
} );