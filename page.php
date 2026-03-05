<?php get_header(); ?>
<?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>
<section id="slider-introduction">

    <div class="container">
        <div class="col-md-10 col-md-offset-1">
            <h1>
                <?php the_title(); ?>
            </h1>
        </div>
    </div>


    <div id="slider-container" class="owl-carousel owl-theme">

        <div class="owl-slide" style="background: url(https://www.forum-montreux.ch/wp-content/uploads/2017/03/forum-1024x178.png);"></div>

    </div>


</section>

<style>

    .block-content{padding-top:50px;padding-bottom:50px;}
    .padding-bottom-50{
        padding-bottom:50px;
    }
    
    .btnemplacement{
        text-align: center;
    padding: 15px;
    background: transparent;
    color: #0d5cac;
    line-height: 70px;
        border:1px solid #0d5cac;
        font-weight:bold;
        margin-bottom:15px;
        cursor:pointer;
    }
    .btnemplacement.active, .btnemplacement:hover{
         background: #0d5cac;
        color: #fff;
    }
    
    .btnblock{
        margin-bottom:50px;
    }
    
    .booking_form .form-group .controls select, .booking_form select, .booking_form textarea, .booking_form input[type="text"] {
    width: 100%;
}
    
    .modal-body {
    max-height: calc(100vh - 210px);
    overflow-y: auto;
}
</style>


<script>
jQuery(function($){
    
    $('#emplacement1').click(function(){
        $('#emplacement1').addClass('active');
        $('#emplacement2').removeClass('active');
        $('#emplacement3').removeClass('active');
        $('#emplacement4').removeClass('active');
        $('#content2').hide();
        $('#content3').hide();
        $('#content4').hide();
        $('#content1').fadeIn();
    });
    $('#emplacement2').click(function(){
        $('#emplacement1').removeClass('active');
        $('#emplacement2').addClass('active');
        $('#emplacement3').removeClass('active');
        $('#emplacement4').removeClass('active');
        $('#content1').hide();
        $('#content3').hide();
        $('#content4').hide();
        $('#content2').fadeIn();
    });
    $('#emplacement3').click(function(){
        $('#emplacement1').removeClass('active');
        $('#emplacement2').removeClass('active');
        $('#emplacement3').addClass('active');
        $('#emplacement4').removeClass('active');
        $('#content1').hide();
        $('#content2').hide();
        $('#content4').hide();
        $('#content3').fadeIn();
    });
    $('#emplacement4').click(function(){
        $('#emplacement1').removeClass('active');
        $('#emplacement2').removeClass('active');
        $('#emplacement3').removeClass('active');
        $('#emplacement4').addClass('active');
        $('#content1').hide();
        $('#content2').hide();
        $('#content3').hide();
        $('#content4').fadeIn();
    });
    
    $('.term_and_condition1').click(function(){
        
        console.log('Trigger modal');
         $('#cg').modal('show');
    })
   

    
    $( "#nbrjour1" ).change(function() {
      
       /* 
        if(this.value == '3 jours'){
            console.log('Vous avez sélectionné 3 jours');
            bk_days_selection_mode    = 'fixed';
            bk_1click_mode_days_num   = 3;
            bk_1click_mode_days_start = [1,4];
        } else {
            console.log('Vous avez sélectionné 6 jours');
            bk_days_selection_mode    = 'fixed';
            bk_1click_mode_days_num   = 6;
            bk_1click_mode_days_start = [1];
            
        }*/
    });
    
    
})
    
    
</script>


<section class="block-content" style="max-width:900px;width:100%;margin:auto">
    <div class="container-fluid container-clean">



        <div class="col-sm-12 padding-bottom-50">
           
            <?php the_content(); ?>
                
        </div>
        
        <div class="col-sm-12 btnblock">
        
        <div class="row padding-bottom-50">
        
            <div class="col-sm-6">
            
                <div id="emplacement1" class="btnemplacement">
                    <?php $photo = get_field('photo_emplacement_1');
                    $thumb = $photo['sizes'][ 'emplacement' ];?>
                    
                    
                    <img src="<?php echo $thumb; ?>" class="img-responsive" />
                    
                    
                    RC (59m2)</div>
            
            </div>
            <div class="col-sm-6">
            
                <div id="emplacement2" class="btnemplacement">
                    <?php $photo = get_field('photo_emplacement_2');
                    $thumb = $photo['sizes'][ 'emplacement' ];?>
                    
                    
                    <img src="<?php echo $thumb; ?>" class="img-responsive" />
                    RS P1 (17m2)</div>
            
            </div>
            <div class="col-sm-6">
            
                <div id="emplacement3" class="btnemplacement">
                    <?php $photo = get_field('photo_emplacement_3');
                    $thumb = $photo['sizes'][ 'emplacement' ];?>
                    
                    
                    <img src="<?php echo $thumb; ?>" class="img-responsive" />RS P2 (20m2)</div>
                
            </div>
            <div class="col-sm-6">
            
                <div id="emplacement4" class="btnemplacement">
                    <?php $photo = get_field('photo_emplacement_4');
                    $thumb = $photo['sizes'][ 'emplacement' ];?>
                    
                    
                    <img src="<?php echo $thumb; ?>" class="img-responsive" />RS P3 (25m2)</div>
                
            </div>
        </div>
            
       
            <div id="content1" style="display:none;">
            
                <?php the_field('emplacement_1'); ?>
            
            
            </div>
            <div id="content2" style="display:none;">
            
                <?php the_field('emplacement_2'); ?>
            
            
            </div>
            <div id="content3" style="display:none;">
            
                <?php the_field('emplacement_3'); ?>
            
            
            </div>
            <div id="content4" style="display:none;">
            
                <?php the_field('emplacement_4'); ?>
            
            
            </div>
        </div>





    </div>
</section>


<div id="cg" class="modal fade" tabindex="-1" role="dialog">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title">Conditions générales</h4>
      </div>
      <div class="modal-body">
       <?php the_field('cg'); ?>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal">Fermer</button>
      </div>
    </div><!-- /.modal-content -->
  </div><!-- /.modal-dialog -->
</div><!-- /.modal -->
<?php endwhile; endif; ?>
<?php get_footer(); ?>
