(function () {
  'use strict';

  function value(selector) {
    var field = document.querySelector(selector);
    return field ? String(field.value || '').trim() : '';
  }

  function contentTitle() {
    if (window.wp && wp.data && wp.data.select('core/editor')) {
      var editorTitle = wp.data.select('core/editor').getEditedPostAttribute('title');
      if (editorTitle) return String(editorTitle).trim();
    }
    return value('#title, .editor-post-title__input');
  }

  function plainText(html) {
    var wrapper = document.createElement('div');
    wrapper.innerHTML = String(html || '');
    return String(wrapper.textContent || wrapper.innerText || '').replace(/\s+/g, ' ').trim();
  }

  function articleSummary() {
    if (window.wp && wp.data && wp.data.select('core/editor')) {
      var editor = wp.data.select('core/editor');
      var excerpt = plainText(editor.getEditedPostAttribute('excerpt'));
      if (excerpt) return excerpt;
      var content = plainText(editor.getEditedPostContent ? editor.getEditedPostContent() : editor.getEditedPostAttribute('content'));
      if (content) return content;
    }
    return plainText(value('#excerpt') || value('#content'));
  }

  function activityLabel(type, fallback) {
    var activity = String(type || fallback || '').trim();
    if (activity && !/^(اجرا|اجرای|طراحی|ساخت|چاپ|بازسازی|نورپردازی)/.test(activity)) {
      activity = 'اجرای ' + activity;
    }
    return activity;
  }

  function trimText(text, maxLength) {
    var characters = Array.from(String(text || '').replace(/\s+/g, ' ').trim());
    return characters.length > maxLength ? characters.slice(0, maxLength - 1).join('') + '…' : characters.join('');
  }

  function buildSuggestions(editor) {
    var title = contentTitle();
    if (editor.dataset.contentType === 'article') {
      var summary = articleSummary();
      return {
        title: trimText((title ? title + ' | زیگورات' : editor.dataset.suggestedTitle) || '', 70),
        description: trimText(summary || editor.dataset.suggestedDescription || (title ? 'در این مطلب از زیگورات با ' + title + ' و نکات کاربردی مرتبط آشنا شوید.' : ''), 158),
        focus: title || editor.dataset.suggestedFocus || '',
        alt: title || editor.dataset.suggestedAlt || ''
      };
    }
    var client = value('[name="project_client"]');
    var city = value('[name="project_city"]');
    var type = value('[name="project_type"]');
    var activity = activityLabel(type, title);
    var seoTitle = [activity, client].filter(Boolean).join(' ');
    if (city) seoTitle += ' در ' + city;
    if (seoTitle) seoTitle += ' | زیگورات';
    var subject = client ? 'پروژه ' + client : (title ? 'پروژه ' + title : 'این پروژه');
    var description = 'طراحی و اجرای ' + subject;
    if (city) description += ' در ' + city;
    if (type) description += ' در زمینه ' + type;
    description += ' توسط زیگورات. تصاویر، مشخصات و جزئیات اجرای پروژه را مشاهده کنید.';
    var focus = activity + (city ? ' در ' + city : '');
    var alt = (activity || 'اجرای پروژه') + (client ? ' ' + client : '') + (city ? ' در ' + city : '');
    return {
      title: trimText(seoTitle || editor.dataset.suggestedTitle || '', 70),
      description: trimText(description || editor.dataset.suggestedDescription || '', 158),
      focus: focus || editor.dataset.suggestedFocus || '',
      alt: alt || editor.dataset.suggestedAlt || ''
    };
  }

  function initialize(editor) {
    if (!editor || editor.dataset.seoReady === '1') return;
    editor.dataset.seoReady = '1';
    var title = editor.querySelector('#zigurat-seo-title');
    var description = editor.querySelector('#zigurat-seo-description');
    var focus = editor.querySelector('#zigurat-seo-focus');
    var alt = editor.querySelector('#zigurat-seo-image-alt');
    var slug = editor.querySelector('#zigurat-seo-slug');
    var titleCount = editor.querySelector('[data-seo-title-count]');
    var descriptionCount = editor.querySelector('[data-seo-description-count]');
    var previewTitle = editor.querySelector('[data-seo-preview-title]');
    var previewDescription = editor.querySelector('[data-seo-preview-description]');
    var previewUrl = editor.querySelector('[data-seo-preview-url]');

    function refresh() {
      var suggestions = buildSuggestions(editor);
      if (titleCount && title) titleCount.textContent = Array.from(title.value).length.toLocaleString('fa-IR');
      if (descriptionCount && description) descriptionCount.textContent = Array.from(description.value).length.toLocaleString('fa-IR');
      if (previewTitle) previewTitle.textContent = (title && title.value.trim()) || suggestions.title || contentTitle();
      if (previewDescription) previewDescription.textContent = (description && description.value.trim()) || suggestions.description;
      if (previewUrl) previewUrl.textContent = editor.dataset.contentBaseUrl + ((slug && slug.value.trim()) || (editor.dataset.contentType === 'article' ? 'article-slug' : 'project-slug')) + '/';
    }

    editor.querySelectorAll('input, textarea').forEach(function (field) {
      field.addEventListener('input', refresh);
    });
    document.querySelectorAll('[name="project_client"], [name="project_city"], [name="project_type"], #title, .editor-post-title__input').forEach(function (field) {
      field.addEventListener('input', refresh);
    });
    var generate = editor.querySelector('[data-zigurat-seo-generate]');
    if (generate) {
      generate.addEventListener('click', function () {
        var suggestions = buildSuggestions(editor);
        if (title) title.value = suggestions.title;
        if (description) description.value = suggestions.description;
        if (focus) focus.value = suggestions.focus;
        if (alt) alt.value = suggestions.alt;
        refresh();
        generate.textContent = 'پیشنهاد ساخته شد ✓';
        window.setTimeout(function () { generate.textContent = 'ساخت دوباره پیشنهاد'; }, 1800);
      });
    }
    refresh();
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.zigurat-seo-editor').forEach(initialize);
  });
}());
