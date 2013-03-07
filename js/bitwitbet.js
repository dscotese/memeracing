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
    
    // Add counters to text area inputs
    if($('textarea').length > 0)
    {
        var n = 'cnt'+$('textarea')[0].name;
        var countDiv = jQuery('<div>140 characters left</div>',
        {
            'style':'position:absolute; margin-top:-16px;',
            'id':n
        });
        $('textarea').before(countDiv);
        $('textarea').keyup(function()
        {
            var myName = this.name;
            countDiv.text(140-this.value.length +" characters left");
            this.style.borderColor = (this.value.length > 140) ? 'red' : '#52A8EC';
        });

        // Let user know to edit their input
        $('span.editme').attr('title','Click to Edit')
            .css('text-decoration', 'underline')
            .css('cursor','pointer');
        $('span.editme').click(function(event)
        {
            $('textarea').val($(this).text());
            countDiv.text(140-$(this).text().length);
            $("input[name='edit']").val(this.id.substr(1));
            $('html, body').animate({ 
               scrollTop: $(document).height()-$(window).height()}, 
               500
            );
        });
    }
} );